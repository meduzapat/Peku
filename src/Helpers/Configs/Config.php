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

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Abstract configuration base class
 *
 * Provides structure for configuration implementations with one-time loading
 * and type-safe access. Implementations define how to import configuration data.
 */
abstract class Config implements Configurable {

	/**
	 * Configuration data storage (loaded once)
	 * @var array<string, array<string, mixed>> $data ['section' => ['key' => value]]
	 */
	protected array $data;

	/**
	 * Initialize and load configuration
	 *
	 * @param array<string, string> $sourceInfo Source-specific information for import
	 * @example ['filename' => 'config.ini'] or ['env_prefix' => 'APP_']
	 * @throws ConfigException If configuration cannot be retrieved
	 */
	public function __construct(array $sourceInfo) {
		$this->data = $this->import($sourceInfo);
	}

	/**
	 * @see \Peku\Helpers\Configs\Configurable::get()
	 */
	public function get(string $section, string $key, mixed $default = null): mixed {
		return $this->data[$section][$key] ?? $default;
	}

	/**
	 * @see \Peku\Helpers\Configs\Configurable::getSection()
	 */
	public function getSection(string $section, array $default = []): array {
		return $this->data[$section] ?? $default;
	}

	/**
	 * @see \Peku\Helpers\Configs\Configurable::hasSection()
	 */
	public function hasSection(string $section): bool {
		return isset($this->data[$section]);
	}

	/**
	 * @see \Peku\Helpers\Configs\Configurable::has()
	 */
	public function has(string $section, string $key): bool {
		return isset($this->data[$section][$key]);
	}

	/**
	 * @see \Peku\Helpers\Configs\Configurable::getAll()
	 */
	public function getAll(): array {
		return $this->data;
	}

	/**
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator($this->data);
	}

	/**
	 * Import configuration from source
	 *
	 * Implementations must load and parse configuration data, returning
	 * a structured array: ['section' => ['key' => value]]
	 *
	 * @param array $sourceInfo Source-specific loading information
	 * @return array Parsed configuration data
	 * @throws ConfigException If configuration cannot be loaded or parsed
	 */
	abstract protected function import(array $sourceInfo): array;
}
