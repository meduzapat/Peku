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
 * Abstract base logger with convenience methods and formatting utilities
 */
abstract class Logger implements Loggeable {

	/**
	 * Log a debug message
	 */
	public function logDebug(mixed $message): void {
		$this->log($message, LogLevel::Debug);
	}

	/**
	 * Log an info message
	 */
	public function logInfo(mixed $message): void {
		$this->log($message, LogLevel::Info);
	}

	/**
	 * Log a warning message
	 */
	public function logWarning(mixed $message): void {
		$this->log($message, LogLevel::Warning);
	}

	/**
	 * Log an error message
	 */
	public function logError(mixed $message): void {
		$this->log($message, LogLevel::Error);
	}

	/**
	 * Log a critical message
	 */
	public function logCritical(mixed $message): void {
		$this->log($message, LogLevel::Critical);
	}

	/**
	 * Format any message type to string for logging
	 *
	 * @param mixed $message Message to format
	 * @return string Formatted message
	 */
	protected function formatMessage(mixed $message): string {
		return match (true) {
			\is_string($message)                  => $message,
			\is_null($message) ||
			\is_scalar($message)                  => \var_export($message, true),
			$message instanceof \Throwable        => $this->formatException($message),
			$message instanceof \Stringable       => (string) $message,
			$message instanceof \JsonSerializable ||
			\is_array($message)                   => $this->formatArray($message),
			\is_object($message)                  => $this->formatObject($message),
			default                               => \print_r($message, true),
		};
	}

	/**
	 * Format array and json aware objects using JSON (single-line)
	 *
	 * @param array $data Array to format
	 * @return string Formatted array
	 */
	protected function formatArray(array|\JsonSerializable $data): string {
		$json = \json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ($json === false) {
			return '[JSON encoding error: ' . \json_last_error_msg() . ']';
		}

		return $json;
	}

	/**
	 * Logs visible properties from bare objects
	 * @param object $obj
	 * @return string
	 */
	protected function formatObject(object $obj): string {
		return $this->formatArray(\get_object_vars($obj));
	}

	/**
	 * Format exception without stack trace
	 *
	 * @param \Throwable $exception Exception to format
	 * @return string Formatted exception string
	 */
	protected function formatException(\Throwable $exception): string {
		return \sprintf(
			'%s: %s in %s:%d',
			\get_class($exception),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine()
		);
	}
}
