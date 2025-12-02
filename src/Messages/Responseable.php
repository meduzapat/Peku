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

namespace Peku\Messages;

/**
 * Response interface for all response implementations
 *
 * Defines public contract for response handling across different contexts
 * (HTTP, CLI, etc.)
 */
interface Responseable {

	/**
	 * Set response body content
	 *
	 * @param mixed $content Content to send (string, array, object)
	 * @return self For method chaining
	 */
	public function setContent(mixed $content): static;

	/**
	 * Get response body content
	 *
	 * @return mixed Response content
	 */
	public function getContent(): mixed;

	/**
	 * Set response result code
	 *
	 * Code meaning is context-specific:
	 * - HTTP: Status code (200, 404, 500, etc.)
	 * - CLI: Exit code (0=success, 1+=error)
	 * - Custom: Implementation-defined
	 *
	 * @param int $code Result code
	 * @return self For method chaining
	 */
	public function setCode(int $code): static;

	/**
	 * Get response result code
	 *
	 * Code meaning is context-specific:
	 * - HTTP: Status code (200, 404, 500, etc.)
	 * - CLI: Exit code (0=success, 1+=error)
	 * - Custom: Implementation-defined
	 *
	 * @return int Result code
	 */
	public function getCode(): int;

	/**
	 * Get human-readable message for result code
	 *
	 * Implementations define what codes mean in their context:
	 * - HTTP: "OK", "Not Found", "Internal Server Error"
	 * - CLI: "Success", "Error"
	 * - Custom: Implementation-defined messages
	 *
	 * @return string Human-readable message
	 */
	public function getCodeMessage(): string;

	/**
	 * Send response to client
	 * @return void
	 */
	public function send(): void;

	/**
	 * Inquiry a request to extract necessary information.
	 * @param Requestable $request
	 */
	public function inquiry(Requestable $request): void;

}

