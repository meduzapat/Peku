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
 * Syslog logger implementation
 *
 * Writes logs to system syslog using native PHP syslog() functions.
 * Automatically opens/closes syslog connection per log call for reliability.
 *
 * @example new Syslog('MyApp', LOG_PID | LOG_PERROR, LOG_USER);
 */
class Syslog extends Logger {

	/**
	 * @var string A name to use on the system log.
	 */
	private string $ident;

	/**
	 * @var int Options to use while logging.
	 */
	private int $option;

	/**
	 * @var int What log to use.
	 */
	private int $facility;

	/**
	 * Initialize syslog logger
	 *
	 * @param string $ident Application identifier (appears in logs)
	 * @param int $option Syslog options (LOG_PID, LOG_PERROR, etc.)
	 * @param int $facility Syslog facility (LOG_USER, LOG_LOCAL0-7, etc.)
	 */
	public function __construct(
		string $ident = 'PekuApp',
		int $option = LOG_PID | LOG_ODELAY,
		int $facility = LOG_USER
	) {
		$this->ident    = $ident;
		$this->option   = $option;
		$this->facility = $facility;
	}

	/**
	 * Log message to syslog
	 *
	 * @see \Peku\Helpers\Loggers\Loggeable::log()
	 */
	public function log(mixed $message, LogLevel $level): void {
		// Use base class formatting
		$formattedMessage = $this->formatMessage($message);

		// Map LogLevel to syslog priority
		$priority = $this->mapPriority($level);

		// Write to syslog
		openlog($this->ident, $this->option, $this->facility);
		syslog($priority, $formattedMessage);
		closelog();
	}

	/**
	 * Map LogLevel enum to syslog priority constants
	 *
	 * @param LogLevel $level Log level
	 * @return int Syslog priority constant
	 */
	private function mapPriority(LogLevel $level): int {
		return match ($level) {
			LogLevel::Debug    => LOG_DEBUG,
			LogLevel::Info     => LOG_INFO,
			LogLevel::Warning  => LOG_WARNING,
			LogLevel::Error    => LOG_ERR,
			LogLevel::Critical => LOG_CRIT
		};
	}
}
