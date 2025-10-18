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
 * Logger interface for all logging implementations
 */
interface Loggeable {

	/**
	 * Log a message with specified level
	 *
	 * @param mixed $message Message to log (string, array, object, etc.)
	 * @param LogLevel $level Log level (info, warning, error, debug, critical)
	 */
	public function log(mixed $message, LogLevel $level): void;
}
