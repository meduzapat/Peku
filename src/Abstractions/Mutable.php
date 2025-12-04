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
 * Interface for key-value data modification
 *
 * Provides common contract for add, remove and clear data by keys across different contexts.
 */
interface Mutable {

	/**
	 * Set value for key
	 *
	 * @param string $key Key name
	 * @param mixed $value Value to set
	 * @return static For method chaining
	 */
	public function set(string $key, mixed $value): static;

	/**
	 * Remove key from collection
	 *
	 * No-op if key doesn't exist.
	 *
	 * @param string $key Key name
	 * @return static For method chaining
	 */
	public function remove(string $key): static;

	/**
	 * Clear all items from collection
	 *
	 * @return static For method chaining
	 */
	public function clear(): static;

	/**
	 * Merge items into collection
	 *
	 * @param array $items Items to merge
	 * @return static For method chaining
	 */
	public function merge(array $items): static;
}
