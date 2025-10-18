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

namespace Peku\Helpers\Files;

use RuntimeException;

/**
 * File system operation exception
 */
class FileException extends RuntimeException {

	/**
	 * @var string The path including the filename.
	 */
	private string $filePath;

	/**
	 * Create file exception
	 *
	 * @param string $filePath File or directory path
	 * @param string $why Optional reason
	 */
	public function __construct(string $filePath, string $why = '') {
		$this->filePath = $filePath;
		$message = $why ? sprintf('%s: %s', $why, $filePath) : sprintf('File operation failed: %s', $filePath);
		parent::__construct($message);
	}

	/**
	 * Get the file path that caused the exception
	 */
	public function getFilePath(): string {
		return $this->filePath;
	}
}
