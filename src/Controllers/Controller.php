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

namespace Peku\Controllers;

/**
 * Abstract base controller providing core functionality
 *
 * Provides logging, configuration, and naming infrastructure for all
 * controller types (web, CLI, programs, etc.) with zero-cost abstractions
 * when logger/config are not used
 */
abstract class Controller {

	/**
	 * @var string The controller name.
	 */
	private string $name;

	/**
	 * Initialize controller and extract class name
	 */
	public function __construct() {
		// Extract controller name from fully qualified class name (last segment)
		$parts      = \explode('\\', static::class);
		$this->name = \end($parts);
	}

	/**
	 * @return string The controller class name
	 */
	final public function getName(): string {
		return $this->name;
	}
}
