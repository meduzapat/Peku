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
 * Trait providing helper methods for Retrievable implementations
 *
 * Implements common filtering and checking operations using the all() method.
 * Classes using this trait must implement Retrievable interface.
 */
trait RetrievableHelpers {

	/**
	 * Get multiple values by keys
	 *
	 * Returns associative array with requested keys. Missing keys are omitted
	 * unless $defaults provided.
	 *
	 * @param array $keys     Array of key names to retrieve
	 * @param array $defaults Optional defaults for missing keys
	 * @return array Associative array of found values
	 */
	public function only(array $keys, array $defaults = []): array {
		// Use array_intersect_key for performance
		$flipped = \array_flip($keys);
		$result  = \array_intersect_key($this->all(), $flipped);

		// Merge defaults for missing keys
		if ($defaults) {
			$result = \array_merge($defaults, $result);
		}

		return $result;
	}

	/**
	 * Get all data except specified keys
	 *
	 * @param array $keys Keys to exclude
	 * @return array Filtered data
	 */
	public function except(array $keys): array {
		// Use array_diff_key for performance
		$flipped = \array_flip($keys);
		return \array_diff_key($this->all(), $flipped);
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
		return !empty(\array_intersect_key($this->all(), $flipped));
	}

	/**
	 * Check if all given keys exist
	 *
	 * @param array $keys Keys to check
	 * @return bool True if all keys exist
	 */
	public function hasAll(array $keys): bool {
		// Use array_diff for performance - empty result means all exist
		return empty(\array_diff($keys, \array_keys($this->all())));
	}
}
