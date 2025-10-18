<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Helpers\Utils\Data;

use Peku\Helpers\Utils\StaticUtility;

/**
 * Static utility class for value type conversions and validations
 *
 * Provides type-safe casting with PHP filters, JSON/serialization conversion,
 * and intelligent type inference based on default values.
 */
final class Values extends StaticUtility {

	/**
	 * Cast string value to match default type with intelligent parsing
	 *
	 * Casting priority:
	 * 1. Array defaults:   Try JSON decode, then PHP unserialize
	 * 2. Boolean defaults: Use FILTER_VALIDATE_BOOLEAN
	 * 3. Integer defaults: Use FILTER_VALIDATE_INT
	 * 4. Float defaults:   Use FILTER_VALIDATE_FLOAT
	 * 5. String defaults:  Return as-is
	 * 6. Other types:      Return default (no casting)
	 *
	 * Uses $default as fallback on parse failure.
	 *
	 * @param string $value   Value to cast
	 * @param mixed  $default Default value (determines target type and fallback)
	 * @return mixed Casted value matching default type
	 */
	public static function cast(string $value, mixed $default): mixed {
		return match (gettype($default)) {
			'array'   => self::toArray($value, $default),
			'integer' => filter_var($value, FILTER_VALIDATE_INT,     FILTER_NULL_ON_FAILURE) ?? $default,
			'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default,
			'double'  => filter_var($value, FILTER_VALIDATE_FLOAT,   FILTER_NULL_ON_FAILURE) ?? $default,
			'string'  => $value === '' ? $default : $value,
			default   => $default,
		};
	}

	/**
	 * Attempts to detect the data type from a string and return the value converted.
	 * @param string $value
	 * @return mixed
	 */
	public static function inferType(string $value): mixed {

		if ($value === '') {
			return $value;
		}

		// Json
		try {
			$detected = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
			if (is_array($detected)) {
				return $detected;
			}
		}
		catch (\JsonException $e) {}

		// Serialized.
		$detected = @unserialize($value, ['allowed_classes' => false]);
		if (is_array($detected)) {
			return $detected;
		}

		// Int
		$detected = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
		if ($detected !== null) {
			return $detected;
		}

		// Float
		$detected = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
		if ($detected !== null) {
			return $detected;
		}

		// Boolean
		$detected = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($detected !== null) {
			return $detected;
		}

		// String
		return $value;
	}

	/**
	 * Convert string to array (tries JSON, then serialization)
	 *
	 * @param string $value String value
	 * @return array Parsed array or empty array on failure
	 */
	public static function toArray(string $value, array $default = []): array {
		try {
			$decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		catch (\JsonException $e) {}

		$unserialized = @unserialize($value, ['allowed_classes' => false]);
		return is_array($unserialized) ? $unserialized : $default;
	}
}
