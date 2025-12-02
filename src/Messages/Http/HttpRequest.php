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
use Peku\Helpers\Utils\Data\Values;

/**
 * HTTP request implementation with metadata and context-aware data access
 */
class HttpRequest extends Request {

	protected array
		$server  = [],
		$query   = [],
		$data    = [],
		$files   = [],
		$headers = [];

	protected string
		$scheme          = '',
		$protocolVersion = '',
		$host            = '',
		$url             = '',
		$uri             = '',
		$referer         = '',
		$remoteIp        = '';

	/**
	 * Extract HTTP request data and metadata
	 * @see Request::extract()
	 */
	protected function extract(): void {
		// 1. Detect request type
		$method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$this->type = RequestType::tryFrom($method) ?? RequestType::Get;

		// 2. Extract via engine
		$extractor   = $this->createExtractor();
		$this->query = $extractor->getQuery();
		$this->data  = $extractor->getData();
		$this->files = $extractor->getFiles();

		// 3. Merge for unified access via get()
		//    POST overrides GET if same key exists
		$this->values = [...$this->query, ...$this->data];

		// 4. Extract HTTP metadata
		$this->protocol = $this->detectProtocol();
		$this->host     = $_SERVER['HTTP_HOST']     ?? '';
		$this->uri      = $_SERVER['REQUEST_URI']   ?? '';
		$this->referer  = $_SERVER['HTTP_REFERER']  ?? '';
		$this->remoteIp = $this->detectRemoteIp();
		$this->headers  = $this->extractHeaders();

		// 5. Build URL (scheme + host + path, NO query string)
		$path      = parse_url($this->uri, PHP_URL_PATH) ?? '/';
		$this->url = $this->protocol . '://' . $this->host . $path;
	}

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
		$acceptHeader = $this->getHeader('Accept') ?? '';

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
	 * Returns the first (highest priority) accepted MIME type,
	 * or 'text/html' if none specified.
	 *
	 * @return string Preferred MIME type
	 *
	 * @example $request->wants(); // 'application/json'
	 */
	public function wants(): string {
		$accepted = $this->accepts();
		return $accepted[0] ?? 'text/html';
	}

	// ========================================================================
	// HTTP Data Access (context-specific)
	// ========================================================================

	/**
	 * Get GET parameters only
	 *
	 * @return array Query string parameters
	 */
	public function getQuery(): array {
		return $this->query;
	}

	/**
	 * Get query parameter (GET only) by key
	 *
	 * @param string $key Parameter name
	 * @param mixed  $default Default value if key not found
	 * @return mixed Parameter value (casted if default provided) or default
	 */
	public function getFromQuery(string $key, mixed $default = null): mixed {
		if (!$this->hasQuery($key)) {
			return $default;
		}

		$value = $this->query[$key];

		if (!\is_string($value)) {
			return $value;
		}

		return $default !== null ? Values::cast($value, $default) : $value;
	}

	/**
	 * Check if query parameter exists (GET)
	 *
	 * @param string $key Parameter name
	 * @return bool True if key exists in GET parameters
	 */
	public function hasQuery(string $key): bool {
		return \array_key_exists($key, $this->query);
	}

	/**
	 * Get POST/PUT/PATCH parameters only
	 *
	 * @return array Request body parameters
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * Get body parameter (POST/PUT/PATCH only) by key
	 *
	 * @param string $key Parameter name
	 * @param mixed  $default Default value if key not found
	 * @return mixed Parameter value (casted if default provided) or default
	 */
	public function getFromData(string $key, mixed $default = null): mixed {
		if (!$this->hasData($key)) {
			return $default;
		}

		$value = $this->data[$key];

		if (!\is_string($value)) {
			return $value;
		}

		return $default !== null ? Values::cast($value, $default) : $value;
	}

	/**
	 * Check if body parameter exists (POST/PUT/PATCH)
	 *
	 * @param string $key Parameter name
	 * @return bool True if key exists in body parameters
	 */
	public function hasData(string $key): bool {
		return \array_key_exists($key, $this->data);
	}

	/**
	 * Get uploaded files
	 *
	 * @return array Uploaded files as File instances
	 */
	public function getFiles(): array {
		return $this->files;
	}

	// ========================================================================
	// HTTP Metadata Access
	// ========================================================================

	/**
	 * Get protocol (http or https)
	 */
	public function getProtocol(): string {
		return $this->protocol;
	}

	/**
	 * Get protocol version
	 */
	public function getProtocolVersion(): string {
		$serverProtocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
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
		$requested = $this->getHeader('X-Requested-With') ?? '';
		return strtolower($requested) === 'xmlhttprequest';
	}

	/**
	 * Get HTTP header by name (case-insensitive)
	 *
	 * @param string $name Header name (e.g., 'Content-Type', 'accept')
	 * @return string Header value or '' if not found
	 */
	public function getHeader(string $name, string $default = ''): string {
		$normalized = ucwords(strtolower($name), '-');
		return $this->headers[$normalized] ?? $default;
	}

	/**
	 * Get all HTTP headers
	 *
	 * @return array All headers in Title-Case format
	 */
	public function getHeaders(): array {
		return $this->headers;
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
	 * Detect HTTP protocol with proxy awareness
	 *
	 * @return string 'http' or 'https'
	 */
	private function detectProtocol(): string {
		$https = $_SERVER['HTTPS'] ?? '';

		if ($https === 'on' || $https === '1') {
			return 'https';
		}

		// Check forwarded proto (behind proxy)
		$forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
		if (strtolower($forwarded) === 'https') {
			return 'https';
		}

		// Check port
		$port = (int)($_SERVER['SERVER_PORT'] ?? 80);
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
			$_SERVER['HTTP_CF_CONNECTING_IP']    ?? '', // Cloudflare
			$_SERVER['HTTP_X_FORWARDED_FOR']     ?? '', // Standard proxy
			$_SERVER['HTTP_X_REAL_IP']           ?? '', // Nginx
			$_SERVER['REMOTE_ADDR']              ?? '', // Direct connection
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
	 * Extract and normalize HTTP headers from $_SERVER
	 *
	 * Converts HTTP_* superglobal keys to proper Title-Case header names.
	 *
	 * @return array Normalized headers
	 */
	private function extractHeaders(): array {
		$headers = [];

		foreach ($_SERVER as $key => $value) {
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
