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

namespace Peku\Helpers\Errors;

use Peku\Helpers\Loggers\Loggeable;
use Peku\Helpers\Loggers\Noop;
use Peku\Helpers\Loggers\LogLevel;
use Peku\Helpers\Utils\StaticUtility;

/**
 * Global error and exception handler with automatic logging
 *
 * Converts PHP errors, exceptions, and fatal errors into structured log entries.
 * Output-agnostic design - only logs technical details, views handle user messaging.
 *
 * @example ErrorHandler::initialize($logger);
 */
final class ErrorHandler extends StaticUtility {

	/**
	 * Error levels set_error_handler() can never receive. Only these can still be
	 * pending at shutdown, so only these are handleFatal()'s responsibility.
	 */
	private const UNCATCHABLE = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING;

	private static Loggeable $logger;

	/**
	 * Initialize error handling and set logger
	 *
	 * @param Loggeable|null $logger Logger instance (defaults to Noop)
	 * @param int            $level  Levels to report; the handler honours this mask
	 */
	public static function initialize(?Loggeable $logger = null, int $level = E_ALL): void {
		self::$logger = $logger ?? new Noop();
		\error_reporting($level);
		\set_error_handler([self::class, 'handleError']);
		\set_exception_handler([self::class, 'handleException']);
		\register_shutdown_function([self::class, 'handleFatal']);
	}

	/**
	 * Handle PHP errors and convert to log entries
	 *
	 * @param int    $errno   Error level
	 * @param string $errstr  Error message
	 * @param string $errfile File where error occurred
	 * @param int    $errline Line number
	 */
	public static function handleError(
		int $errno,
		string $errstr,
		string $errfile,
		int $errline
	): bool {

		// Masked by error_reporting() or silenced with @. The caller said it does not
		// want this reported, so reporting it anyway would be wrong.
		if ((\error_reporting() & $errno) !== 0) {
			self::report($errno, $errstr, $errfile, $errline);
		}

		// Always claim the error. Returning false hands it to PHP's internal handler,
		// which records it as the last error and makes handleFatal() log it a second
		// time at shutdown.
		return true;
	}

	/**
	 * Handle uncaught exceptions
	 *
	 * @param \Throwable $exception Uncaught exception
	 */
	public static function handleException(\Throwable $exception): void {
		self::$logger->log($exception, LogLevel::Critical);
	}

	/**
	 * Handle fatal errors via shutdown function
	 */
	public static function handleFatal(): void {

		$error = error_get_last();  // unqualified: php-mock intercepts only unqualified calls

		// Anything handleError() could receive, it already reported. Only the
		// uncatchable levels are still outstanding here.
		if ($error === null || ($error['type'] & self::UNCATCHABLE) === 0) {
			return;
		}

		// Reported unconditionally: a fatal is worth recording whatever the mask says.
		self::report(
			$error['type'],
			$error['message'],
			$error['file'],
			$error['line']
		);
	}

	/**
	 * Format and write one error to the log
	 *
	 * @param int    $errno   Error level
	 * @param string $errstr  Error message
	 * @param string $errfile File where the error occurred
	 * @param int    $errline Line number
	 */
	private static function report(
		int $errno,
		string $errstr,
		string $errfile,
		int $errline
	): void {

		$message = \sprintf(
			'%s [%d]: %s in %s:%d',
			self::getErrorTypeName($errno),
			$errno,
			$errstr,
			\basename($errfile),
			$errline
		);
		self::$logger->log($message, self::mapErrorToLogLevel($errno));
	}

	/**
	 * Map PHP error level to LogLevel
	 *
	 * @param int $errno PHP error constant
	 * @return LogLevel Corresponding log level
	 */
	private static function mapErrorToLogLevel(int $errno): LogLevel {
		return match ($errno) {
			E_WARNING,
			E_NOTICE,
			E_CORE_WARNING,
			E_COMPILE_WARNING,
			E_USER_WARNING,
			E_USER_NOTICE,
			E_DEPRECATED,
			E_USER_DEPRECATED   => LogLevel::Warning,

			E_ERROR,
			E_PARSE,
			E_CORE_ERROR,
			E_COMPILE_ERROR,
			E_USER_ERROR,
			E_RECOVERABLE_ERROR => LogLevel::Critical,

			default             => LogLevel::Error,
		};
	}

	/**
	 * Get human-readable error type name
	 *
	 * @param int $errno PHP error constant
	 * @return string Error type name
	 */
	private static function getErrorTypeName(int $errno): string {
		return match ($errno) {
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parse Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
			E_DEPRECATED        => 'Deprecated',
			E_USER_DEPRECATED   => 'User Deprecated',
			default             => 'Unknown Error',
		};
	}
}
