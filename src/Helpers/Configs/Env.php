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

namespace Peku\Helpers\Configs;

use Peku\Helpers\Utils\Data\Values;

/**
 * Environment variable configuration implementation
 *
 * Reads configuration from environment variables with type-safe defaults.
 * Environment variable names are constructed as: [PREFIX_]SECTION_KEY (uppercase).
 *
 * Supports type casting based on default values:
 * - int, float: numeric conversion
 * - bool: "true"/"false", "1"/"0", "yes"/"no", "on"/"off"
 * - string: no conversion
 *
 * @example
 * $config = new Env([
 *     'database' => [
 *         'host' => 'localhost',    // Reads DATABASE_HOST
 *         'port' => 3306,           // Reads DATABASE_PORT (casts to int)
 *         'debug' => false,         // Reads DATABASE_DEBUG (casts to bool)
 *     ],
 *     'app' => [
 *         'name' => 'MyApp',        // Reads APP_NAME
 *     ],
 * ]);
 *
 * // With prefix:
 * $config = new Env([...], 'MYAPP'); // Reads MYAPP_DATABASE_HOST, etc.
 */
class Env extends Config {

	private string $prefix;

	/**
	 * Initialize environment configuration with defaults
	 *
	 * @param array  $dataToRetrieve Configuration structure with optional default values
	 * @param string $prefix         Optional prefix for environment variables
	 */
	public function __construct(array $dataToRetrieve, string $prefix = '') {
		$this->prefix = $prefix ? \strtoupper($prefix) . '_' : '';
		parent::__construct($dataToRetrieve);
	}

	/**
	 * Import configuration from environment variables
	 * @see Config::import()
	 */
	protected function import(array $sourceInfo): array {
		$config   = [];
		foreach ($sourceInfo as $section => $keys) {
			if (!\is_array($keys)) {
				throw new ConfigException("Section '$section' must be an array");
			}

			$sectionData = [];
			foreach ($keys as $key => $default) {
				// Detect format
				if (\is_int($key)) {
					$hasDefault = false;
					$actualKey = $default;
				}
				else {
					$hasDefault = true;
					$actualKey = $key;
				}

				$envName = $this->buildEnvName($section, $actualKey);
				$value   = $this->getEnvValue($envName);

				if ($value === null) {
					if (!$hasDefault) {
						throw new ConfigException("Missing required environment variable: $envName");
					}
					$value = $default;
				}
				elseif ($hasDefault) {
					$value = Values::cast($value, $default);
				}
				$sectionData[$actualKey] = $value;
			}
			$config[$section] = $sectionData;
		}

		return $config;
	}

	/**
	 * Build environment variable name from section and key
	 *
	 * @param string $section Section name
	 * @param string $key     Key name
	 * @return string Environment variable name (e.g., "PREFIX_SECTION_KEY")
	 */
	private function buildEnvName(string $section, string $key): string {
		$name = \strtoupper($section . '_' . $key);
		return $this->prefix . $name;
	}

	/**
	 * Get environment variable value
	 *
	 * Checks both $_ENV and getenv() for maximum compatibility
	 *
	 * @param string $name Environment variable name
	 * @return string|null Value or null if not found
	 */
	private function getEnvValue(string $name): ?string {
		$value = \getenv($name);
		return $value !== false ? $value : null;
	}
}
