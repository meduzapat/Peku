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

use Peku\Helpers\Files\FileException;

/**
 * File logger implementation
 *
 * Writes logs to a specified file with timestamps and log levels.
 *
 * @example new File('/var/log/app.log');
 */
class File extends Logger {

	private string $filePath;

	/**
	 * Initialize file logger
	 *
	 * @param string $filePath Path to log file
	 * @param bool $createDirIfMissing if set will attempt to create a missing dir.
	 * @throws \Peku\Helpers\Files\FileException If file path is not writable
	 */
	public function __construct(string $filePath, bool $createDirIfMissing = false) {
		$this->filePath = $filePath;
		$this->ensureWritable($createDirIfMissing);
	}

	/**
	 * Log message to file
	 * @see \Peku\Helpers\Loggers\Loggeable::log()
	 */
	public function log(mixed $message, LogLevel $level): void {
		$formattedMessage = $this->formatMessage($message);
		$timestamp        = date('Y-m-d H:i:s');
		$logEntry         = sprintf("[%s] [%s] %s\n", $timestamp, $level->value, $formattedMessage);

		file_put_contents($this->filePath, $logEntry, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Ensure file path is writable
	 *
	 * @param bool $createDirIfMissing if true the dir will be created if not existing
	 * @throws \Peku\Helpers\Files\FileException If directory doesn't exist or file not writable
	 */
	private function ensureWritable(bool $createDirIfMissing): void {
		// Check existing file
		if (file_exists($this->filePath)) {
			if (is_writable($this->filePath)) {
				return;
			}
			throw new FileException($this->filePath, 'Log file is not writable');
		}

		$dir = dirname($this->filePath);

		if (!is_dir($dir)) {
			if ($createDirIfMissing) {
				if (!@mkdir($dir, 0755, true)) {
					throw new FileException($dir, 'Failed to create log directory');
				}
			}
			else {
				throw new FileException($dir, 'Log directory does not exist');
			}
		}
		if (!is_writable($dir)) {
			throw new FileException($dir, 'Log directory is not writable');
		}
	}
}
