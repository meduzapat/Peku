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

namespace Peku\Abstractions;

/**
 * Interface for key-value data retrieval
 *
 * Provides common contract for accessing data by keys across different
 * contexts (requests, configuration, collections, etc.)
 */
interface Retrievable {

	/**
	 * Get value by key with optional default
	 *
	 * @param string $key     Key name
	 * @param mixed  $default Default value if key not found
	 * @return mixed Value or default
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Check if key exists
	 *
	 * @param string $key Key name
	 * @return bool True if key exists
	 */
	public function has(string $key): bool;

	/**
	 * Get all data
	 *
	 * @return array All data as associative array
	 */
	public function all(): array;
}
