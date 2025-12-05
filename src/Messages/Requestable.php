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

use Peku\Abstractions\Collection;

/**
 * Request interface for all request implementations
 *
 * Defines public contract for request handling across different contexts
 * (HTTP, CLI, etc.)
 */
interface Requestable {

	/**
	 * Get request type
	 *
	 * @return RequestType Request type enum (GET, POST, CLI, etc.)
	 */
	public function getType(): RequestType;

	/**
	 * Get request values collection
	 *
	 * Provides read-only access to all request data with type casting support.
	 *
	 * @return Collection Request data collection
	 */
	public function values(): Collection;

	/**
	 * Determine desired response format
	 *
	 * @return string Format identifier (html, json, xml, txt, etc.)
	 */
	public function wants(): string;
}
