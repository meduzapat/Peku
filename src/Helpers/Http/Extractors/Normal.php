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

use Peku\Helpers\Files\{File, FileInterface, FileException};
use Peku\Helpers\Http\{UploadedFile, UploadException};

/**
 * Standard PHP extractor using superglobals
 *
 * Uses PHP's built-in request parsing ($_GET, $_POST, $_FILES).
 * Subject to PHP's parameter name mangling (dots/spaces become underscores).
 *
 * For requests with dots/spaces in parameter names, use Advanced extractor instead.
 */
class Normal extends Extractor {

	/**
	 * Initialize by securing data from PHP superglobals
	 * @see Extractor::initialize()
	 */
	protected function initialize(): void {
		// Secure data: move from superglobals to internal storage
		$this->parameters = $_GET;
		$this->values     = $_POST;
		$this->files      = $this->normalizeFiles($_FILES);

		// Optional: Clear superglobals to prevent accidental raw access
		// Uncomment if you want to enforce using HttpRequest exclusively
		// $_GET = $_POST = $_FILES = [];
	}

	/**
	 * Normalize $_FILES array to UploadedFile objects
	 *
	 * Validates uploads, performs security checks, and wraps files with upload metadata.
	 * Invalid uploads are silently skipped.
	 *
	 * @param array $files Raw $_FILES array
	 * @return array<string, UploadedFile|array<UploadedFile>> Normalized uploaded files
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
				$normalized[$name] = $this->createFileFromUpload($fileData);
			}
		}

		return $normalized;
	}

	/**
	 * Create UploadedFile from upload data with validation
	 *
	 * @param array $fileData Single file data from $_FILES
	 * @return UploadedFile Wrapped uploaded file with metadata
	 */
	private function createFileFromUpload(array $fileData): UploadedFile {
		$clientFilename = $fileData['name'];
		$clientPath     = $fileData['full_path'] ?? $fileData['name']; // PHP 8.1+ support
		$error          = $fileData['error'];

		// Upload failed - return UploadedFile with error, no physical file
		if ($error !== UPLOAD_ERR_OK) {
			return new UploadedFile(null, $error, $clientFilename, $clientPath);
		}

		// Security check - ensure file was uploaded via HTTP POST
		if (!\is_uploaded_file($fileData['tmp_name'])) {
			return new UploadedFile(null, UPLOAD_ERR_CANT_WRITE, $clientFilename, $clientPath);
		}

		// TODO: Additional security checks
		// - Validate $fileData['size'] against max upload size
		// - Scan for malicious content

		try {
			// Manage physical temp file (stays in PHP temp directory for auto-cleanup)
			$file = File::manage($fileData['tmp_name']);
			return new UploadedFile($file, UPLOAD_ERR_OK, $clientFilename, $clientPath);
		}
		catch (FileException $e) {
			// File management failed (shouldn't happen with valid uploads)
			return new UploadedFile(null, UPLOAD_ERR_CANT_WRITE, $clientFilename, $clientPath);
		}
	}

	/**
	 * Normalize multiple file uploads into UploadedFile objects
	 *
	 * PHP transforms input like: <input type="file" name="docs[]" multiple>
	 * Into: ['name' => ['file1', 'file2'], 'type' => [...], ...]
	 *
	 * @param array $fileData Raw file data array
	 * @return array<UploadedFile> Array of UploadedFile objects
	 */
	private function normalizeFileArray(array $fileData): array {
		$files = [];
		$count = \count($fileData['name']);

		for ($i = 0; $i < $count; $i++) {
			$singleFileData = [
				'name'      => $fileData['name'][$i],
				'type'      => $fileData['type'][$i],
				'tmp_name'  => $fileData['tmp_name'][$i],
				'error'     => $fileData['error'][$i],
				'size'      => $fileData['size'][$i],
				'full_path' => $fileData['full_path'][$i] ?? $fileData['name'][$i], // PHP 8.1+
			];

			$files[] = $this->createFileFromUpload($singleFileData);
		}

		return $files;
	}
}