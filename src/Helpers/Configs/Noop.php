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

/**
 * Noop config implementation - returns []
 */
class Noop extends Config {

	/**
	 * Get configuration value (always returns default)
	 * @see Config::import()
	 */
	protected function import(array $sourceInfo): array {
		return [];
	}
}
