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
 *
 * Provides HTTP-specific functionality:
 * - Protocol detection (HTTP/HTTPS with proxy awareness)
 * - Header normalization and access
 * - Remote IP detection (proxy-aware with validation)
 * - Separated GET/POST data access
 * - File upload handling
 * - URL/URI distinction per RFC standard
 */
class HttpRequest extends Request {

	private array
	$query   = [], // GET parameters
	$data    = [], // POST/PUT/PATCH parameters
	$files   = []; // Uploaded files

	private string
	$protocol = '',
	$host     = '',
	$url      = '',
	$uri      = '',
	$referer  = '',
	$remoteIp = '';

	private array $headers = [];

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
		$this->values = array_merge($this->query, $this->data);

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
	 * @see Request::wants()
	 */
	public function wants(): string {
		$accept = $this->getHeader('Accept') ?? 'text/html';

		return match (true) {
			str_contains($accept, 'application/json') => 'json',
			str_contains($accept, 'application/xml')  => 'xml',
			str_contains($accept, 'text/plain')       => 'txt',
			default                                   => 'html',
		};
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
	 * @return string|null Header value or null if not found
	 */
	public function getHeader(string $name): ?string {
		// Normalize: Content-Type, content-type, CONTENT-TYPE -> Content-Type
		$normalized = str_replace(' ', '-', ucwords(str_replace('-', ' ', strtolower($name))));
		return $this->headers[$normalized] ?? null;
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