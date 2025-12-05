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
 * Mutable collection with write operations
 *
 * Extends Collection to provide write capabilities while maintaining type casting for string values.
 */
class MutableCollection extends Collection implements Mutable {

	/**
	 * Set value for key
	 *
	 * @param string $key Key name
	 * @param mixed $value Value to set
	 * @return static
	 */
	public function set(string $key, mixed $value): static {
		$this->items[$key] = $value;
		return $this;
	}

	/**
	 * Remove key from collection
	 *
	 * @param string $key Key name
	 * @return static
	 */
	public function remove(string $key): static {
		unset($this->items[$key]);
		return $this;
	}

	/**
	 * Clear all items from collection
	 *
	 * @return static
	 */
	public function clear(): static {
		$this->items = [];
		return $this;
	}

	/**
	 * Merge items into collection (overwrites existing keys)
	 *
	 * @param array<string, mixed> $items Items to merge
	 * @return static
	 */
	public function merge(array $items): static {
		$this->items = [...$this->items, ...$items];
		return $this;
	}

	/**
	 * Get and remove value by key with optional type casting
	 *
	 * Uses same casting logic as get(). Removes key after retrieval if it exists.
	 *
	 * @param string $key Key name
	 * @param mixed $default Default value (determines casting type for strings)
	 * @return mixed Value (casted if string with default) or default
	 */
	public function pull(string $key, mixed $default = null): mixed {
		$value = $this->get($key, $default);
		if ($this->has($key)) {
			$this->remove($key);
		}
		return $value;
	}
}
