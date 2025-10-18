<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

namespace Peku\Helpers\Configs;

use IteratorAggregate;

/**
 * @extends IteratorAggregate<string, array<string, mixed>>
 */
interface Configurable extends IteratorAggregate {

	/**
	 * Get configuration value by section and key
	 *
	 * @param string $section Configuration section
	 * @param string $key     Configuration key
	 * @param mixed  $default Default value if not found
	 * @return mixed Configuration value or default
	 */
	public function get(string $section, string $key, mixed $default = null): mixed;

	/**
	 * Get entire configuration section
	 *
	 * @param string $section Configuration section
	 * @param array  $default Default array if section not found
	 * @return array Section data or default
	 */
	public function getSection(string $section, array $default = []): array;

	/**
	 * Check if section exists
	 *
	 * @param string $section Section name
	 * @return bool True if section exists
	 */
	public function hasSection(string $section): bool;

	/**
	 * Check if key exists in section
	 *
	 * @param string $section Section name
	 * @param string $key     Key name
	 * @return bool True if key exists
	 */
	public function has(string $section, string $key): bool;

	/**
	 * Get all configuration data
	 *
	 * @return array Complete configuration array
	 */
	public function getAll(): array;
}
