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

use Peku\Messages\{Response, Requestable};

/**
 * HTTP response implementation with headers and status code management
 *
 * Provides HTTP-specific functionality:
 * - Header management (Content-Type, Cache-Control, etc.)
 * - HTTP status code handling
 * - Output buffering and sending
 * - Convenience methods for common responses
 */
class HttpResponse extends Response {

	/**
	 * @var array<string, string> HTTP headers
	 */
	private array $headers = [];

	/**
	 * Initialize HTTP response
	 *
	 * @param mixed $content Optional content
	 * @param int   $status  Optional HTTP status code (default: 200)
	 * @param array $headers Optional initial headers
	 */
	public function __construct(
		mixed $content = '',
		int   $status  = 200,
		array $headers = []
	) {
		parent::__construct($content, $status);
		$this->headers = $headers;
	}

	/**
	 * Set HTTP header
	 *
	 * @param string $name  Header name (e.g., 'Content-Type')
	 * @param string $value Header value
	 * @return self For method chaining
	 */
	public function setHeader(string $name, string $value): static {
		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * Get HTTP header by name (case-insensitive)
	 *
	 * @param string $name Header name (e.g., 'Content-Type', 'accept')
	 * @return string Header value or '' if not found
	 */
	public function getHeader(string $name, string $default = ''): string {
		$normalized = \ucwords(\strtolower($name), '-');
		return $this->headers[$normalized] ?? $default;
	}

	/**
	 * Check if header exists
	 *
	 * @param string $name Header name
	 * @return bool True if header is set
	 */
	public function hasHeader(string $name): bool {
		return isset($this->headers[$name]);
	}

	/**
	 * Remove HTTP header
	 *
	 * @param string $name Header name
	 * @return self For method chaining
	 */
	public function removeHeader(string $name): static {
		unset($this->headers[$name]);
		return $this;
	}

	/**
	 * Get all HTTP headers
	 *
	 * @return array<string, string> All headers
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Set multiple headers at once
	 *
	 * @param array<string, string> $headers Headers to set
	 * @return self For method chaining
	 */
	public function setHeaders(array $headers): static {
		$this->headers = [...$this->headers, ...$headers];
		return $this;
	}

	/**
	 * Set Content-Type header
	 *
	 * @param string $contentType MIME type (e.g., 'application/json')
	 * @param string $charset     Optional charset (default: 'utf-8')
	 * @return self For method chaining
	 */
	public function setContentType(string $contentType, string $charset = 'utf-8'): static {
		$value = $charset !== '' ? "$contentType; charset=$charset" : $contentType;
		return $this->setHeader('Content-Type', $value);
	}

	/**
	 * Set Cache-Control header
	 *
	 * @param string $value Cache directive (e.g., 'no-cache', 'max-age=3600')
	 * @return self For method chaining
	 */
	public function setCacheControl(string $value): static {
		return $this->setHeader('Cache-Control', $value);
	}

	/**
	 * Disable caching
	 *
	 * @return self For method chaining
	 */
	public function noCache(): static {
		return $this->setHeaders([
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma'        => 'no-cache',
			'Expires'       => '0',
		]);
	}

	/**
	 * Get human-readable message for HTTP status code
	 *
	 * @see Response::getCodeMessage()
	 */
	public function getCodeMessage(): string {
		return StatusCodes::getStatusMessage($this->code);
	}

	// ========================================================================
	// Convenience Response Methods
	// ========================================================================

	/**
	 * Create success response (200 OK)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function ok(mixed $content = ''): static {
		return new static($content, 200);
	}

	/**
	 * Create created response (201 Created)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function created(mixed $content = ''): static {
		return new static($content, 201);
	}

	/**
	 * Create no content response (204 No Content)
	 *
	 * @return self New response instance
	 */
	public static function noContent(): self {
		return new static('', 204);
	}

	/**
	 * Create bad request response (400 Bad Request)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function badRequest(mixed $content = ''): static {
		return new static($content, 400);
	}

	/**
	 * Create unauthorized response (401 Unauthorized)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function unauthorized(mixed $content = ''): static {
		return new static($content, 401);
	}

	/**
	 * Create forbidden response (403 Forbidden)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function forbidden(mixed $content = ''): static {
		return new static($content, 403);
	}

	/**
	 * Create not found response (404 Not Found)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function notFound(mixed $content = ''): static {
		return new static($content, 404);
	}

	/**
	 * Create server error response (500 Internal Server Error)
	 *
	 * @param mixed $content Response content
	 * @return self New response instance
	 */
	public static function serverError(mixed $content = ''): static {
		return new static($content, 500);
	}

	/**
	 * @see \Peku\Messages\Responseable::inquiry()
	 */
	public function inquiry(Requestable $request): void {
		if ($request instanceof HttpRequest) {
			$this->setProtocol('HTTP/' . $request->getProtocolVersion());
		}
	}

	/**
	 * Check if headers have been sent
	 *
	 * @return bool True if headers were sent
	 */
	public function areHeadersSent(): bool {
		return headers_sent();
	}

	protected function validate(mixed $content): void {
		if (\is_string($content) || $content instanceof \Stringable) {
			return;
		}

		throw new \InvalidArgumentException(
			'HTTP response content must be string or Stringable. ' .
			'Received: ' . \get_debug_type($content)
		);
	}

	/**
	 * Send HTTP status and headers
	 */
	protected function sendHeaders(): void {
		if ($this->areHeadersSent()) {
			return;
		}
		$protocol      = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
		$statusMessage = $this->getCodeMessage();
		header("$protocol $this->code $statusMessage", true, $this->code);

		foreach ($this->headers as $name => $value) {
			header("$name: $value", true);
		}
	}

	/**
	 * @see \Peku\Messages\Response::processContent()
	 */
	protected function processContent(): string {
		$this->sendHeaders();
		return (string) $this->content;
	}
}
