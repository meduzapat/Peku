<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author	Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link	  https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Helpers\Loggers;

/**
 * Noop logger implementation - does nothing (zero-cost abstraction)
 *
 * Used as default when no logger is configured, allowing the PHP
 * optimizer to potentially eliminate dead code paths
 */
class Noop extends Logger {

	/**
	 * Log a message (no-op)
	 * Intentionally empty - zero cost when logging is not needed
	 * @see \Peku\Helpers\Loggers\Logger::log()
	 */
	public function log(mixed $message, LogLevel $level): void {}
}
