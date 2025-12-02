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

namespace Peku\Messages\Http;

class StatusCodes {

	private const CODES = [
		// 2xx Success
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		204 => 'No Content',

		// 3xx Redirection
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',

		// 4xx Client Errors
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		409 => 'Conflict',
		422 => 'Unprocessable Entity',
		429 => 'Too Many Requests',

		// 5xx Server Errors
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout'
	];

	/**
	 * Get HTTP status message for code
	 *
	 * @param int $code Status code
	 * @return string Status message
	 */
	static public function getStatusMessage(int $code): string {
		return self::CODES[$code] ?? 'Unknown Status Code';
	}
}
