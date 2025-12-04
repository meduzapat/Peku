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
 * Extends Collection to provide write capabilities.
 */
class MutableCollection extends Collection implements Mutable {

	/**
	 * Set value for key
	 *
	 * @param string $key Key name
	 * @param mixed $value Value to set
	 * @return static For method chaining
	 */
	public function set(string $key, mixed $value): static {
		$this->items[$key] = $value;
		return $this;
	}

	/**
	 * Remove key from collection
	 *
	 * No-op if key doesn't exist.
	 *
	 * @param string $key Key name
	 * @return static For method chaining
	 */
	public function remove(string $key): static {
		unset($this->items[$key]);
		return $this;
	}

	/**
	 * Clear all items from collection
	 *
	 * @return static For method chaining
	 */
	public function clear(): static {
		$this->items = [];
		return $this;
	}

	/**
	 * Merge items into collection (overwrites existing keys)
	 *
	 * @param array<string, mixed> $items Items to merge
	 * @return static For method chaining
	 */
	public function merge(array $items): static {
		$this->items = array_merge($this->items, $items);
		return $this;
	}

	/**
	 * Get and remove value by key with optional type casting
	 *
	 * Uses same casting logic as get(). Removes key if it exists.
	 *
	 * @param string $key Key name
	 * @param mixed $default Default value (determines casting type)
	 * @return mixed Value (casted if applicable) or default if missing
	 */
	public function pull(string $key, mixed $default = null): mixed {
		$value = $this->get($key, $default);
		if ($this->has($key)) {
			$this->remove($key);
		}
		return $value;
	}
}
