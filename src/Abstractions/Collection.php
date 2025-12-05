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

use Peku\Helpers\Utils\Data\Values;

/**
 * Read-only collection with mixed value support
 *
 * Stores and retrieves associative array data with optional type casting for string values.
 */
class Collection implements Retrievable, \Countable, \IteratorAggregate {

	/**
	 * @var array<string, mixed> Internal items storage
	 */
	protected array $items = [];

	/**
	 * Initialize collection with items
	 *
	 * @param array<string, mixed> $items Initial items
	 */
	public function __construct(array $items = []) {
		$this->items = $items;
	}

	/**
	 * Get value by key with optional type casting
	 *
	 * Casting rules:
	 * 1. Key missing → return default
	 * 2. Key exists, no default → return value as-is
	 * 3. Key exists, value non-string → return value as-is
	 * 4. Key exists, value is string, default provided → cast to default's type
	 *
	 * @param string $key Key name
	 * @param mixed $default Default value (determines casting type for strings)
	 * @return mixed Value (casted if string with default) or default
	 */
	public function get(string $key, mixed $default = null): mixed {
		if (!$this->has($key)) {
			return $default;
		}
		$value = $this->items[$key];
		if ($default === null) {
			return $value;
		}
		if (!\is_string($value)) {
			return $value;
		}
		return Values::cast($value, $default);
	}

	/**
	 * Check if key exists
	 *
	 * @param string $key Key name
	 * @return bool True if key exists (even if value is null)
	 */
	public function has(string $key): bool {
		return \array_key_exists($key, $this->items);
	}

	/**
	 * Get all items
	 *
	 * @return array All items as associative array
	 */
	public function all(): array {
		return $this->items;
	}

	/**
	 * Get all keys
	 *
	 * @return string[] Array of keys
	 */
	public function keys(): array {
		return \array_keys($this->items);
	}

	/**
	 * Get all values (reindexed)
	 *
	 * @return array Array of values with numeric keys
	 */
	public function values(): array {
		return \array_values($this->items);
	}

	/**
	 * Check if collection is empty
	 *
	 * @return bool True if no items
	 */
	public function isEmpty(): bool {
		return empty($this->items);
	}

	/**
	 * Get first value
	 *
	 * @return mixed First value or null if empty
	 */
	public function first(): mixed {
		return $this->items[array_key_first($this->items)] ?? null;
	}

	/**
	 * Get last value
	 *
	 * @return mixed Last value or null if empty
	 */
	public function last(): mixed {
		return $this->items[\array_key_last($this->items)] ?? null;
	}

	/**
	 * Get multiple values by keys
	 *
	 * @param array $keys Array of key names to retrieve
	 * @param array $defaults Optional defaults for missing keys (only keys in $keys are used)
	 * @return static New collection with requested keys. Missing keys use defaults if provided, else omitted.
	 */
	public function only(array $keys, array $defaults = []): static {
		$flipped = array_flip($keys);
		$result = array_intersect_key($this->items, $flipped);
		if ($defaults) {
			$result = array_merge(array_intersect_key($defaults, $flipped), $result);
		}
		return new static($result);
	}

	/**
	 * Get all items except specified keys
	 *
	 * @param array $keys Keys to exclude
	 * @return array Filtered items
	 */
	public function except(array $keys): static {
		$flipped = array_flip($keys);
		$result = array_diff_key($this->items, $flipped);
		return new static($result);
	}

	/**
	 * Check if any of the given keys exist
	 *
	 * @param array $keys Keys to check
	 * @return bool True if any key exists
	 */
	public function hasAny(array $keys): bool {
		// Use array_intersect_key for performance
		$flipped = \array_flip($keys);
		return !empty(\array_intersect_key($this->items, $flipped));
	}

	/**
	 * Check if all given keys exist (values can be null)
	 *
	 * @param array $keys Keys to check
	 * @return bool True if all keys exist
	 */
	public function hasAll(array $keys): bool {
		// Use array_diff for performance - empty result means all exist
		return empty(\array_diff($keys, \array_keys($this->items)));
	}

	/**
	 * @see \Countable::count()
	 */
	public function count(): int {
		return \count($this->items);
	}

	/**
	 * @see \IteratorAggregate::getIterator()
	 */
	public function getIterator(): \Traversable {
		return new \ArrayIterator($this->items);
	}
}
