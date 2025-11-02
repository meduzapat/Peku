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

use Peku\Abstractions\RetrievableHelpers;

/**
 * Abstract base request providing unified interface for all request types
 *
 * Defines common contract for HTTP, CLI, and other request contexts.
 * Implementations handle context-specific data sources (superglobals, argv, etc.)
 */
abstract class Request {

	use RetrievableHelpers;

	/**
	 * @var RequestType Request type (GET, POST, CLI, etc.)
	 */
	protected RequestType $type;

	/**
	 * @var array Extracted and sanitized request data
	 */
	protected array $values = [];

	/**
	 * Initialize request and load data from source
	 */
	public function __construct() {
		$this->extract();
	}

	/**
	 * Extract request data from context-specific source
	 *
	 * Called during construction. Implementations load data from:
	 * - HTTP: $_GET, $_POST, $_SERVER, etc.
	 * - CLI: $argv, getopt(), etc.
	 *
	 * Must populate:
	 * - $this->type   - Request type enum
	 * - $this->values - Extracted request data
	 */
	abstract protected function extract(): void;

	/**
	 * Get request type
	 *
	 * @return RequestType Request type enum (GET, POST, CLI, etc.)
	 */
	public function getType(): RequestType {
		return $this->type;
	}

	/**
	 * Get input value by key with optional default
	 *
	 * When default is provided, attempts to cast value to match default type
	 * using Values::cast() for type-safe conversions.
	 *
	 * @param string $key Input key name
	 * @param mixed  $default Default value if key not found
	 * @return mixed Input value (casted if default provided) or default
	 */
	public function get(string $key, mixed $default = null): mixed {

		if (!$this->has($key)) {
			return $default;
		}

		if (!\is_string($this->values[$key])) {
			return $this->values[$key];
		}

		// Cast to match default type if default provided and value is string
		return \Peku\Helpers\Utils\Data\Values::cast($this->values[$key], $default ?? '');
	}

	/**
	 * Check if input key exists
	 *
	 * @param string $key Input key name
	 * @return bool True if key exists
	 */
	public function has(string $key): bool {
		return \array_key_exists($key, $this->values);
	}

	/**
	 * Get all input data
	 *
	 * @return array All input data as associative array
	 */
	public function all(): array {
		return $this->values;
	}
}
