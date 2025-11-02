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

use Peku\Messages\Cli\CliRequest;
use Peku\Messages\Http\HttpRequest;

/**
 * Factory for creating appropriate Request instances
 *
 * Auto-detects request context (CLI vs HTTP) and instantiates
 * the appropriate Request implementation
 */
class RequestFactory {

	/**
	 * Auto-detect and create appropriate request from environment
	 *
	 * Determines request type based on SAPI:
	 * - CLI: Returns CliRequest
	 * - HTTP: Returns HttpRequest
	 *
	 * @return Request Request instance (HttpRequest or CliRequest)
	 */
	public static function capture(): Requestable {
		return \php_sapi_name() === 'cli'
			? new CliRequest()
			: new HttpRequest();
	}
}
