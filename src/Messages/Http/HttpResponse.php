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
use Peku\Abstractions\MutableCollection;

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
	 * @var MutableCollection HTTP headers
	 */
	private MutableCollection $headers;

	/**
	 * @var string HTTP protocol version
	 */
	private string $protocol = 'HTTP/1.1';

	/**
	 * Initialize HTTP response
	 *
	 * @param mixed $content Optional content
	 * @param int   $code    Optional HTTP status code (default: 200)
	 * @param array $headers Optional initial headers
	 */
	public function __construct(
		mixed $content = '',
		int   $code    = 200,
		array $headers = []
	) {
		parent::__construct($content, $code);
		$this->headers = new MutableCollection($headers);
	}

	/**
	 * Set HTTP protocol version
	 *
	 * @param string $protocol Protocol version (e.g., 'HTTP/1.1', 'HTTP/2')
	 * @return self For method chaining
	 */
	public function setProtocol(string $protocol): static {
		$this->protocol = $protocol;
		return $this;
	}

	/**
	 * Get HTTP protocol version
	 *
	 * @return string Protocol version
	 */
	public function getProtocol(): string {
		return $this->protocol;
	}

	/**
	 * Get headers collection
	 *
	 * @return MutableCollection Headers collection
	 */
	public function headers(): MutableCollection {
		return $this->headers;
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
		$this->headers->set('Content-Type', $value);
		return $this;
	}

	/**
	 * Set Cache-Control header
	 *
	 * @param string $value Cache directive (e.g., 'no-cache', 'max-age=3600')
	 * @return self For method chaining
	 */
	public function setCacheControl(string $value): static {
		$this->headers->set('Cache-Control', $value);
		return $this;
	}

	/**
	 * Disable caching
	 *
	 * @return self For method chaining
	 */
	public function noCache(): static {
		$this->headers->merge([
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma'        => 'no-cache',
			'Expires'       => '0',
		]);
		return $this;
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

	/**
	 * Validate content type
	 *
	 * @param mixed $content Content to validate
	 * @throws \InvalidArgumentException if content invalid
	 */
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

		$statusMessage = $this->getCodeMessage();
		header("$this->protocol $this->code $statusMessage", true, $this->code);

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
