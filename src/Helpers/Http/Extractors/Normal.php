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
use Peku\Helpers\Http\UploadedFile;

/**
 * Standard PHP extractor using superglobals
 *
 * Extracts all superglobals and clears them for security.
 * Subject to PHP's parameter name mangling (dots/spaces become underscores).
 */
class Normal extends Extractor {

	/**
	 * Extract and secure all superglobals
	 *
	 * @see Extractor::initialize()
	 */
	protected function initialize(): void {
		// Secure all superglobals
		$this->server     = $_SERVER;
		$this->parameters = $_GET;
		$this->values     = $_POST;
		$this->files      = $this->normalizeFiles($_FILES);

		// Clear superglobals (security)
		$_SERVER = $_GET = $_POST = $_FILES = [];
	}

	/**
	 * Normalize $_FILES to UploadedFile objects
	 *
	 * @param array $files Raw $_FILES array
	 * @return array<string, UploadedFile|array<UploadedFile>>
	 */
	private function normalizeFiles(array $files): array {
		$normalized = [];

		foreach ($files as $name => $fileData) {
			if (!isset($fileData['error'])) {
				continue;
			}

			// Multiple files
			if (\is_array($fileData['name'])) {
				$normalized[$name] = $this->normalizeFileArray($fileData);
			}
			// Single file
			else {
				$normalized[$name] = $this->createFileFromUpload($fileData);
			}
		}

		return $normalized;
	}

	/**
	 * Create UploadedFile from upload data
	 *
	 * @param array $fileData Single file data from $_FILES
	 * @return UploadedFile
	 */
	private function createFileFromUpload(array $fileData): UploadedFile {
		$clientFilename = $fileData['name'];
		$clientPath     = $fileData['full_path'] ?? $fileData['name'];
		$error          = $fileData['error'];

		if ($error !== UPLOAD_ERR_OK) {
			return new UploadedFile(null, $error, $clientFilename, $clientPath);
		}

		if (!is_uploaded_file($fileData['tmp_name'])) {
			return new UploadedFile(null, UPLOAD_ERR_CANT_WRITE, $clientFilename, $clientPath);
		}

		try {
			$file = File::manage($fileData['tmp_name']);
			return new UploadedFile($file, UPLOAD_ERR_OK, $clientFilename, $clientPath);
		}
		catch (FileException $e) {
			return new UploadedFile(null, UPLOAD_ERR_CANT_WRITE, $clientFilename, $clientPath);
		}
	}

	/**
	 * Normalize multiple file uploads
	 *
	 * @param array $fileData Raw file data array
	 * @return array<UploadedFile>
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
				'full_path' => $fileData['full_path'][$i] ?? $fileData['name'][$i],
			];

			$files[] = $this->createFileFromUpload($singleFileData);
		}

		return $files;
	}
}
