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

namespace Peku\Helpers\Http\Extractors;

use Peku\Helpers\Files\{File, FileInterface};
use Peku\Helpers\Files\FileException;

/**
 * Standard PHP extractor using superglobals
 *
 * Uses PHP's built-in request parsing ($_GET, $_POST, $_FILES).
 * Subject to PHP's parameter name mangling (dots/spaces become underscores).
 *
 * For requests with dots/spaces in parameter names, use Advance extractor instead.
 */
class Normal extends Extractor {

	/**
	 * Initialize by securing data from PHP superglobals
	 * @see Extractor::initialize()
	 */
	protected function initialize(): void {
		// Secure data: move from superglobals to internal storage
		$this->securedGet   = $_GET;
		$this->securedPost  = $_POST;
		$this->securedFiles = $this->normalizeFiles($_FILES);

		// Optional: Clear superglobals to prevent accidental raw access
		// Uncomment if you want to enforce using HttpRequest exclusively
		// $_GET = $_POST = $_FILES = [];
	}

	/**
	 * Normalize $_FILES array to File objects
	 *
	 * Validates uploads, performs security checks, and moves files to temp location
	 * before creating File objects. Invalid uploads are silently skipped.
	 *
	 * @param array $files Raw $_FILES array
	 * @return array<string, File|array> Normalized files
	 */
	private function normalizeFiles(array $files): array {
		$normalized = [];

		foreach ($files as $name => $fileData) {
			if (!isset($fileData['error'])) {
				continue;
			}

			// Handle multiple file uploads (array notation)
			if (\is_array($fileData['name'])) {
				$normalized[$name] = $this->normalizeFileArray($fileData);
			}
			else {
				// Single file upload
				$file = $this->createFileFromUpload($fileData);
				if ($file !== null) {
					$normalized[$name] = $file;
				}
			}
		}

		return $normalized;
	}

	/**
	 * Create File object from upload data with validation
	 *
	 * @param array $fileData Single file data from $_FILES
	 * @return FileInterface File object
	 * @throws FileException if something goes wrong.
	 */
	private function createFileFromUpload(array $fileData): FileInterface {

		// Validate upload
		if ($fileData['error'] !== UPLOAD_ERR_OK) {
			throw new FileException($fileData['tmp_name'], $fileData['error']);
		}

		// Security check
		if (!\is_uploaded_file($fileData['tmp_name'])) {
			throw new FileException($fileData['tmp_name'], 'The file was not uploaded');
		}

		// TODO: other security checks like Use $fileData['size'], $fileData['type'], etc

		$file = File::manage($fileData['tmp_name']);
		$dir  = \sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'peku_uploaded';
		if (!\file_exists($dir)) {
			@mkdir($dir, 0755);
		}
		$file->move($dir . DIRECTORY_SEPARATOR . $fileData['tmp_name']);
		return $file;
	}

	/**
	 * Normalize multiple file uploads into File objects
	 *
	 * PHP transforms input like: <input type="file" name="docs[]" multiple>
	 * Into: ['name' => ['file1', 'file2'], 'type' => [...], ...]
	 *
	 * @param array $fileData Raw file data array
	 * @return array<File> Array of File objects
	 */
	private function normalizeFileArray(array $fileData): array {
		$files = [];
		$count = \count($fileData['name']);

		for ($i = 0; $i < $count; $i++) {
			$singleFileData = [
				'name'     => $fileData['name'][$i],
				'type'     => $fileData['type'][$i],
				'tmp_name' => $fileData['tmp_name'][$i],
				'error'    => $fileData['error'][$i],
				'size'     => $fileData['size'][$i],
			];

			$files[] = $this->createFileFromUpload($singleFileData);
		}

		return $files;
	}
}