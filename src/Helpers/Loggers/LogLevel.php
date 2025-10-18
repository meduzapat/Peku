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

namespace Peku\Helpers\Loggers;

/**
 * Log level enumeration for type-safe logging
 */
enum LogLevel: string {

	case Debug    = 'debug';
	case Info     = 'info';
	case Warning  = 'warning';
	case Error    = 'error';
	case Critical = 'critical';
}
