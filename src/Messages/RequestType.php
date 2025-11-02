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

namespace Peku\Messages;

/**
 * Request type enumeration for type-safe request identification
 */
enum RequestType: string {
	// HTTP methods
	case Get     = 'GET';
	case Post    = 'POST';
	case Put     = 'PUT';
	case Patch   = 'PATCH';
	case Delete  = 'DELETE';
	case Head    = 'HEAD';
	case Options = 'OPTIONS';

	// CLI/Console
	case Cli     = 'CLI';
}
