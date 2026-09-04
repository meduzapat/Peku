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
use Peku\Abstractions\{Collection, MutableCollection};
use Peku\Validation\Rules;

/**
 * HTTP request implementation with metadata and context-aware data access
 */
class HttpRequest extends Request {

	/**
	 * Trust proxy headers (X-Forwarded-*, CF-Connecting-IP, X-Real-IP)
	 *
	 * WARNING: Only enable if behind trusted reverse proxy/load balancer.
	 * Trusting proxy headers from untrusted sources allows IP spoofing.
	 */
	protected static bool $trustProxies = false;

	protected Collection
		$server,  // Server variables (string-only)
		$headers, // HTTP headers (string-only)
		$query,   // Query parameters (GET) with type casting
		$data;    // Body parameters (POST/PUT/PATCH) with type casting

	/**
	 * Uploaded files (raw array - contains UploadedFile objects)
	 */
	protected array $files = [];

	/**
	 * HTTP metadata
	 */
	protected string
		$scheme   = '',
		$url      = '',
		$uri      = '',
		$remoteIp = '';

	/**
	 * Rules enforced against this request during extraction
	 */
	protected MutableCollection $rules;

	/**
	 * Initialize with the rules to enforce during extraction
	 *
	 * Defaults to a fail-closed set construct explicitly to actually trust anything.
	 * @param MutableCollection|null $rules Rule instances, keyed by name.
	 */
	public function __construct(?MutableCollection $rules = null) {
		$this->rules = $rules ?? self::defaultRules();
		parent::__construct();
	}

	/**
	 * Fail-closed default rule set
	 *
	 * No TrustedHosts configured means allowedHost always fails - there is
	 * no implicit "trust everything" fallback.
	 */
	protected static function defaultRules(): MutableCollection {
		return (new MutableCollection())->set('allowedHost', new AllowedHost(null));
	}

	/**
	 * Enable proxy header trust
	 *
	 * WARNING: Only enable behind trusted reverse proxy/load balancer.
	 * Allows X-Forwarded-*, CF-Connecting-IP, X-Real-IP headers.
	 *
	 * @param bool $trust Trust proxy headers
	 */
	public static function trustProxies(bool $trust = true): void {
		self::$trustProxies = $trust;
	}

	/************************
	 * Collection Accessors *
	 ************************/

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
	 * @return Collection Query parameters with type casting
	 */
	public function query(): Collection {
		return $this->query;
	}

	/**
	 * Get body parameters collection (POST/PUT/PATCH)
	 *
	 * @return Collection Body parameters with type casting
	 */
	public function data(): Collection {
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

	/***********************
	 * Content Negotiation *
	 ***********************/

	/**
	 * Get accepted MIME types sorted by preference
	 *
	 * Parses Accept header with RFC 9110 compliance:
	 * - Quality values (q parameter)
	 * - Specificity rules (exact > type wildcard > full wildcard)
	 * - Rejection handling (q=0)
	 *
	 * @return array Sorted array of MIME types (best first)
	 */
	public function accepts(): array {
		$acceptHeader = $this->headers->get('Accept', '');

		if ($acceptHeader === '') {
			return ['text/html'];
		}

		$parsed   = [];
		$segments = explode(',', $acceptHeader);

		foreach ($segments as $index => $segment) {
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
				$param = trim($param);
				if (0 === stripos($param, 'q=')) {
					$qValueStr = trim(substr($param, strpos($param, '=') + 1));
					if (preg_match('/^(0(\.\d{0,3})?|1(\.0{0,3})?)$/', $qValueStr)) {
						$quality = (float) $qValueStr;
					}
					else {
						// Invalid qvalue: reject
						$quality = 0.0;
					}
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
				$mime === '*/*'            => 1,  // Full wildcard (lowest)
				str_ends_with($mime, '/*') => 2,  // Type wildcard (medium)
				default                    => 3,  // Exact type/subtype (highest)
			};

			$parsed[] = [
				'mime'        => $mime,
				'quality'     => $quality,
				'specificity' => $specificity,
				'order'       => $index,  // Tie-breaker: original position
			];
		}

		// Empty or all rejected - default to text/html
		if (empty($parsed)) {
			return ['text/html']; // Or return [] to signal no acceptable types
		}

		// Sort by: 1) quality DESC, 2) specificity DESC, 3) order ASC (leftmost first)
		usort($parsed, function($a, $b) {
			// Compare quality first (higher is better)
			$qualityDiff = $b['quality'] <=> $a['quality'];
			if ($qualityDiff !== 0) {
				return $qualityDiff;
			}

			// If equal quality, compare specificity (higher is better)
			$specDiff = $b['specificity'] <=> $a['specificity'];
			if ($specDiff !== 0) {
				return $specDiff;
			}

			// If equal, compare original order (lower index is better)
			return $a['order'] <=> $b['order'];
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

	/************************
	 * HTTP Metadata Access *
	 ************************/

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
		return $this->headers->get('Host', '');
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
		return $this->headers->get('Referer', '');
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

	/********************
	 * Internal Helpers *
	 ********************/

	/**
	 * Extract HTTP request data and metadata
	 * @see Request::extract()
	 */
	protected function extract(): void {
		// 1. Extract via engine (secures and clears superglobals)
		$extractor   = $this->createExtractor();
		$server      = new MutableCollection($extractor->getServer());
		$this->query = new Collection($extractor->getQuery());
		$this->data  = new Collection($extractor->getData());
		$this->files = $extractor->getFiles();

		// 2. Detect request type (pull REQUEST_METHOD)
		$method     = $server->pull('REQUEST_METHOD', 'GET');
		$this->type = RequestType::tryFrom($method) ?? RequestType::Get;

		// 3. Extract HTTP metadata BEFORE header extraction
		//    (detectScheme/detectRemoteIp need proxy headers)
		$this->scheme   = $this->detectScheme($server);
		$this->remoteIp = $this->detectRemoteIp($server);
		$this->uri      = $server->pull('REQUEST_URI', '');

		// 4. Extract headers from remaining server variables (pulls HTTP_*, CONTENT_*)
		//    HTTP_HOST and HTTP_REFERER remain in headers collection
		$this->headers = new Collection($this->extractHeaders($server));

		// 5. Enforce configured rules before $url below is built.
		$context = new Collection(['headers' => $this->headers, 'server' => $server]);
		Rules::enforce($this->rules, $context);

		// 6. Build URL (scheme + host + path, NO query string)
		$path      = parse_url($this->uri, PHP_URL_PATH) ?? '/';
		$this->url = $this->scheme . '://' . $this->getHost() . $path;

		// 7. Merge query+data for unified access via values()
		//    POST overrides GET if same key exists
		$this->values = new Collection([...$this->query->all(), ...$this->data->all()]);

		// 8. Store cleaned server variables (extracted metadata removed)
		$this->server = new Collection($server->all());
	}

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
	 * Pulls scheme-related variables from server collection.
	 *
	 * @param MutableCollection $server Server variables
	 * @return string 'http' or 'https'
	 */
	private function detectScheme(MutableCollection $server): string {
		$https = $server->pull('HTTPS', '');

		if ($https === 'on' || $https === '1') {
			return 'https';
		}

		// Check forwarded proto only if trusting proxies
		if (self::$trustProxies) {
			$forwarded = $server->pull('HTTP_X_FORWARDED_PROTO', '');
			if (strtolower($forwarded) === 'https') {
				return 'https';
			}
		}

		// Check port
		$port = $server->pull('SERVER_PORT', 80);
		return $port === 443 ? 'https' : 'http';
	}

	/**
	 * Detect remote IP with proxy awareness and validation
	 *
	 * Pulls IP-related variables from server collection.
	 *
	 * @param MutableCollection $server Server variables
	 * @return string Remote IP address or empty string
	 */
	private function detectRemoteIp(MutableCollection $server): string {
		$candidates = [];

		// Add proxy headers only if trusting proxies
		if (self::$trustProxies) {
			$candidates[] = $server->pull('HTTP_CF_CONNECTING_IP', '');  // Cloudflare CDN
			$candidates[] = $server->pull('HTTP_X_FORWARDED_FOR', '');   // Standard reverse proxy
			$candidates[] = $server->pull('HTTP_X_REAL_IP', '');         // Nginx reverse proxy
		}

		// Always check direct connection
		$candidates[] = $server->pull('REMOTE_ADDR', '');

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
	 * Pulls HTTP_* and CONTENT_* keys from server collection.
	 *
	 * @param MutableCollection $server Server variables
	 * @return array<string, string> Normalized headers
	 */
	private function extractHeaders(MutableCollection $server): array {
		$headers = [];

		foreach ($server as $key => $value) {
			// Convert HTTP_* to proper header names
			if (str_starts_with($key, 'HTTP_')) {
				// HTTP_ACCEPT_LANGUAGE -> Accept-Language
				$name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
				$headers[$name] = $value;
				$server->remove($key);
			}
			// Special cases (not prefixed with HTTP_)
			elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
				$name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
				$headers[$name] = $value;
				$server->remove($key);
			}
		}

		return $headers;
	}
}
