<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Messages\Http;

use Peku\Messages\{Request, RequestType};
use Peku\Helpers\Http\Extractors\{Extractable, Normal};
use Peku\Abstractions\{Collection, MixedCollection};

/**
 * HTTP request implementation with metadata and context-aware data access
 */
class HttpRequest extends Request {

	protected Collection
		//Server variables (string-only)
		$server,
		// HTTP headers (string-only)
		$headers,
		// Query parameters (GET) with type casting
		$query,
		// Body parameters (POST/PUT/PATCH) with type casting
		$data;

	/**
	 * Uploaded files (raw array - contains UploadedFile objects)
	 */
	protected array $files = [];

	/**
	 * HTTP metadata
	 */
	protected string
		$scheme   = '',
		$host     = '',
		$url      = '',
		$uri      = '',
		$referer  = '',
		$remoteIp = '';

	/**
	 * Extract HTTP request data and metadata
	 * @see Request::extract()
	 */
	protected function extract(): void {
		// 1. Extract via engine (secures and clears superglobals)
		$extractor     = $this->createExtractor();
		$this->server  = new Collection($extractor->getServer());
		$this->query   = new MixedCollection($extractor->getQuery());
		$this->data    = new MixedCollection($extractor->getData());
		$this->files   = $extractor->getFiles();

		// 2. Detect request type
		$method     = $this->server->get('REQUEST_METHOD', 'GET');
		$this->type = RequestType::tryFrom($method) ?? RequestType::Get;

		// 3. Extract headers from server variables
		$this->headers = new Collection($this->extractHeaders());

		// 4. Merge for unified access via values()
		//    POST overrides GET if same key exists
		$this->values = new MixedCollection([...$this->query->all(), ...$this->data->all()]);

		// 5. Extract HTTP metadata
		$this->scheme   = $this->detectScheme();
		$this->host     = $this->server->get('HTTP_HOST', '');
		$this->uri      = $this->server->get('REQUEST_URI', '');
		$this->referer  = $this->server->get('HTTP_REFERER', '');
		$this->remoteIp = $this->detectRemoteIp();

		// 6. Build URL (scheme + host + path, NO query string)
		$path      = parse_url($this->uri, PHP_URL_PATH) ?? '/';
		$this->url = $this->scheme . '://' . $this->host . $path;
	}

	// ========================================================================
	// Collection Accessors
	// ========================================================================

	/**
	 * Get server variables collection
	 *
	 * @return Collection Server variables (string-only)
	 */
	public function server(): Collection {
		return $this->server;
	}

	/**
	 * Get query parameters collection (GET)
	 *
	 * @return MixedCollection Query parameters with type casting
	 */
	public function query(): MixedCollection {
		return $this->query;
	}

	/**
	 * Get body parameters collection (POST/PUT/PATCH)
	 *
	 * @return MixedCollection Body parameters with type casting
	 */
	public function data(): MixedCollection {
		return $this->data;
	}

	/**
	 * Get HTTP headers collection
	 *
	 * @return Collection Headers (string-only)
	 */
	public function headers(): Collection {
		return $this->headers;
	}

	/**
	 * Get uploaded files
	 *
	 * @return array Uploaded files as UploadedFile instances
	 */
	public function files(): array {
		return $this->files;
	}

	// ========================================================================
	// Content Negotiation
	// ========================================================================

	/**
	 * Get accepted MIME types sorted by preference
	 *
	 * Parses Accept header with RFC 9110 compliance:
	 * - Quality values (q parameter)
	 * - Specificity rules (exact > type wildcard > full wildcard)
	 * - Rejection handling (q=0)
	 *
	 * @return array Sorted array of MIME types (best first)
	 *
	 * @example
	 * // Accept: text/html;q=0.9, application/json
	 * $request->accepts(); // ['application/json', 'text/html']
	 */
	public function accepts(): array {
		$acceptHeader = $this->headers->get('Accept', '');

		if ($acceptHeader === '') {
			return ['text/html'];
		}

		$parsed   = [];
		$segments = explode(',', $acceptHeader);

		foreach ($segments as $segment) {
			$segment = trim($segment);
			if ($segment === '') {
				continue;
			}

			// Parse: type/subtype; param1=value1; q=0.8; param2=value2
			$parts = explode(';', $segment);
			$mime  = trim($parts[0]);

			// Extract quality (q parameter) - default 1.0
			$quality = 1.0;
			foreach (array_slice($parts, 1) as $param) {
				if (preg_match('/^\s*q\s*=\s*([\d.]+)\s*$/i', $param, $matches)) {
					$quality = (float)$matches[1];
					// Clamp to valid range per RFC 9110 (0.0 - 1.0)
					$quality = max(0.0, min(1.0, $quality));
					break;
				}
			}

			// Skip if quality is 0 (explicitly rejected)
			if ($quality === 0.0) {
				continue;
			}

			// Calculate specificity for precedence (higher = more specific)
			// Per RFC 9110: exact type > type wildcard > full wildcard
			$specificity = match (true) {
				$mime === '*/*'              => 1,  // Full wildcard (lowest)
				str_ends_with($mime, '/*')   => 2,  // Type wildcard (medium)
				default                      => 3,  // Exact type/subtype (highest)
			};

			$parsed[] = [
				'mime'        => $mime,
				'quality'     => $quality,
				'specificity' => $specificity,
			];
		}

		// Empty or all rejected - default to text/html
		if (empty($parsed)) {
			return ['text/html'];
		}

		// Sort by: 1) quality DESC, 2) specificity DESC
		usort($parsed, function($a, $b) {
			// Compare quality first (higher is better)
			$qualityDiff = $b['quality'] <=> $a['quality'];
			if ($qualityDiff !== 0) {
				return $qualityDiff;
			}

			// If equal quality, compare specificity (higher is better)
			return $b['specificity'] <=> $a['specificity'];
		});

			// Extract sorted MIME types
			return array_column($parsed, 'mime');
	}

	/**
	 * Get most preferred MIME type
	 *
	 * @return string Preferred MIME type
	 */
	public function wants(): string {
		$accepted = $this->accepts();
		return $accepted[0] ?? 'text/html';
	}

	// ========================================================================
	// HTTP Metadata Access
	// ========================================================================

	/**
	 * Get URI scheme (http or https)
	 */
	public function getScheme(): string {
		return $this->scheme;
	}

	/**
	 * Get protocol version (1.1, 2, etc.)
	 */
	public function getProtocolVersion(): string {
		$serverProtocol = $this->server->get('SERVER_PROTOCOL', 'HTTP/1.1');
		// Extract version from "HTTP/1.1"
		return substr($serverProtocol, 5) ?: '1.1';
	}

	/**
	 * Get host (domain + port if non-standard)
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * Get URL (scheme + host + path, no query string)
	 *
	 * @example https://example.com/path/to/resource
	 */
	public function getUrl(): string {
		return $this->url;
	}

	/**
	 * Get URI (path + query string)
	 *
	 * @example /path/to/resource?param=value
	 */
	public function getUri(): string {
		return $this->uri;
	}

	/**
	 * Get path component only (no query string)
	 *
	 * @example /path/to/resource
	 */
	public function getPath(): string {
		return parse_url($this->uri, PHP_URL_PATH) ?? '/';
	}

	/**
	 * Get HTTP referer
	 */
	public function getReferer(): string {
		return $this->referer;
	}

	/**
	 * Get remote IP address (proxy-aware with validation)
	 */
	public function getRemoteIp(): string {
		return $this->remoteIp;
	}

	/**
	 * Get HTTP method (GET, POST, PUT, etc.)
	 */
	public function getMethod(): string {
		return $this->type->value;
	}

	/**
	 * Check if request is AJAX (XMLHttpRequest)
	 */
	public function isAjax(): bool {
		$requested = $this->headers->get('X-Requested-With', '');
		return strtolower($requested) === 'xmlhttprequest';
	}

	// ========================================================================
	// Internal Helpers
	// ========================================================================

	/**
	 * Create extractor engine
	 *
	 * Uses custom extractor if set via setDefaultExtractor(),
	 * otherwise defaults to Normal extractor.
	 *
	 * @return Extractable Extractor instance
	 */
	protected function createExtractor(): Extractable {
		return self::$extractor ?? new Normal();
	}

	/**
	 * Detect URI scheme with proxy awareness
	 *
	 * @return string 'http' or 'https'
	 */
	private function detectScheme(): string {
		$https = $this->server->get('HTTPS', '');

		if ($https === 'on' || $https === '1') {
			return 'https';
		}

		// Check forwarded proto (behind proxy)
		$forwarded = $this->server->get('HTTP_X_FORWARDED_PROTO', '');
		if (strtolower($forwarded) === 'https') {
			return 'https';
		}

		// Check port
		$port = (int)$this->server->get('SERVER_PORT', '80');
		return $port === 443 ? 'https' : 'http';
	}

	/**
	 * Detect remote IP with proxy awareness and validation
	 *
	 * Tries multiple headers in order, validates IP format.
	 * Returns first valid IP found, empty string if none.
	 *
	 * @return string Remote IP address or empty string
	 */
	private function detectRemoteIp(): string {
		// Try various headers (proxy-aware)
		$candidates = [
			// Cloudflare CDN
			$this->server->get('HTTP_CF_CONNECTING_IP', ''),
			// Standard reverse proxy
			$this->server->get('HTTP_X_FORWARDED_FOR', ''),
			// Nginx reverse proxy
			$this->server->get('HTTP_X_REAL_IP', ''),
			// Direct connection
			$this->server->get('REMOTE_ADDR', ''),
		];

		foreach ($candidates as $ip) {
			if ($ip !== '') {
				// X-Forwarded-For can be comma-separated (client, proxy1, proxy2)
				$ips = explode(',', $ip);
				$ip  = trim($ips[0]);

				// Try strict validation first (public IPs only)
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
					return $ip;
				}

				// Fallback: accept private IPs (for development: 127.0.0.1, 192.168.x.x)
				if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
					return $ip;
				}
			}
		}

		return '';
	}

	/**
	 * Extract and normalize HTTP headers from server variables
	 *
	 * Converts HTTP_* server keys to proper Title-Case header names.
	 *
	 * @return array Normalized headers
	 */
	private function extractHeaders(): array {
		$headers = [];

		foreach ($this->server as $key => $value) {
			// Convert HTTP_* to proper header names
			if (str_starts_with($key, 'HTTP_')) {
				// HTTP_ACCEPT_LANGUAGE -> Accept-Language
				$name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
				$headers[$name] = $value;
			}
			// Special cases (not prefixed with HTTP_)
			elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
				$name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
				$headers[$name] = $value;
			}
		}

		return $headers;
	}
}