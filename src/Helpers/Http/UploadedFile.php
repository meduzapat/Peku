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

namespace Peku\Helpers\Http;

use Peku\Helpers\Files\{FileInterface, FileException};

/**
 * HTTP uploaded file wrapper with metadata and health tracking
 *
 * Wraps a temporary FileInterface with HTTP-specific metadata:
 * - Original filename from client
 * - Directory path (PHP 8.1+ full_path support)
 * - Upload error tracking with health monitoring
 *
 * Physical file stays in PHP temp directory until explicitly moved.
 * Health checking detects external file deletion and updates state.
 *
 * @example
 * // Single file upload
 * $avatar = $request->getFile('avatar');
 * if ($avatar->isAvailable()) {
 *     $avatar->moveTo('/uploads/avatars/');
 * }
 *
 * @example
 * // Directory upload (PHP 8.1+)
 * $docs = $request->getFiles('docs');
 * foreach ($docs as $doc) {
 *     // Preserves structure: project/src/File.php
 *     $doc->moveTo('/uploads/');
 * }
 *
 * @example
 * // Access file properties or custom operations
 * $file = $avatar->getFile();
 * if ($file) {
 *     $mime = $file->getMimeType();
 *     $size = $file->getSize();
 *     $file->move('/custom/name.jpg');
 * }
 */
class UploadedFile {

	/**
	 * Custom error code for post-upload file deletion/invalidation
	 */
	private const ERROR_FILE_DELETED = 100;

	/**
	 * @var FileInterface|null Physical temp file
	 */
	private ?FileInterface $file;

	/**
	 * @var int Upload error code (UPLOAD_ERR_* or custom)
	 */
	private int $error;

	/**
	 * @var string Original filename from client
	 */
	private string $clientFilename;

	/**
	 * @var string Full client path (for directory uploads)
	 */
	private string $clientPath;

	/**
	 * Create uploaded file wrapper
	 *
	 * @param FileInterface|null $file Physical temp file (null if upload failed)
	 * @param int                $error Upload error code (UPLOAD_ERR_OK = 0 for success)
	 * @param string             $clientFilename Original filename
	 * @param string             $clientPath Full path (defaults to filename if not provided)
	 */
	public function __construct(
		?FileInterface $file,
		int            $error,
		string         $clientFilename,
		string         $clientPath = ''
	) {
		$this->file           = $file;
		$this->error          = $error;
		$this->clientFilename = $clientFilename;
		$this->clientPath     = $clientPath ?: $clientFilename;
	}

	/*******************
	 * Health & Status *
	 *******************/

	/**
	 * Check if file is available for operations
	 *
	 * Performs health check which may update error state if file was
	 * externally deleted or invalidated.
	 *
	 * @return bool True if file is healthy and available
	 */
	public function isAvailable(): bool {
		return $this->checkHealth();
	}

	/**
	 * Get upload error code
	 *
	 * @return int UPLOAD_ERR_* constant or ERROR_FILE_DELETED (100)
	 */
	public function getError(): int {
		return $this->error;
	}

	/**
	 * Check if upload had errors
	 *
	 * @return bool True if error occurred
	 */
	public function hasError(): bool {
		return $this->error !== UPLOAD_ERR_OK;
	}

	/******************************************************
	 * Metadata Access (always safe - no file operations) *
	 ******************************************************/

	/**
	 * Get original filename from client
	 *
	 * @return string Filename (e.g., "document.pdf")
	 */
	public function getClientFilename(): string {
		return $this->clientFilename;
	}

	/**
	 * Get full client path (for directory uploads)
	 *
	 * For directory uploads with PHP 8.1+ full_path support, returns
	 * the complete path structure: "project/src/File.php"
	 *
	 * For single file uploads, returns just the filename.
	 *
	 * @return string Full path or filename
	 */
	public function getClientPath(): string {
		return $this->clientPath;
	}

	/*********************************************
	 * File Access (returns null if unavailable) *
	 *********************************************/

	/**
	 * Get underlying File for manual operations
	 *
	 * Use this for custom move operations with specific naming,
	 * or to access file properties (MIME type, size, etc.)
	 *
	 * @example $uploadedFile->getFile()->move('/custom/path/name.jpg');
	 * @example $mime = $uploadedFile->getFile()?->getMimeType();
	 * @example $size = $uploadedFile->getFile()?->getSize();
	 * @example $uploadedFile->getFile()?->delete();
	 *
	 * @return FileInterface|null File instance or null if unavailable
	 */
	public function getFile(): ?FileInterface {
		return $this->checkHealth() ? $this->file : null;
	}

	/*********************************
	 * Operations (throw on failure) *
	 *********************************/

	/**
	 * Move file preserving client path/filename structure
	 *
	 * Automatically creates directory structure and uses original
	 * filename/path from client.
	 *
	 * @example
	 * // Single file: avatar.jpg
	 * $file->moveTo('/uploads/avatars/');
	 * // Result: /uploads/avatars/avatar.jpg
	 *
	 * @example
	 * // Directory upload: project/src/File.php
	 * $file->moveTo('/uploads/');
	 * // Result: /uploads/project/src/File.php
	 *
	 * @param string $baseDir Base directory for deployment
	 * @throws UploadException if file unavailable or move fails
	 */
	public function moveTo(string $baseDir): void {
		if (!$this->checkHealth()) {
			throw new UploadException("Cannot move file: {$this->getErrorMessage()}");
		}

		// Build full destination path
		$destination = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->clientPath;

		// Create directory structure if needed
		$dir = dirname($destination);
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true)) {
				throw new UploadException("Failed to create directory: $dir");
			}
		}

		// Move file
		try {
			$this->file->move($destination);
		}
		catch (FileException $e) {
			throw new UploadException("Failed to move uploaded file: " . $e->getMessage(), 0, $e);
		}
	}

	/******************************
	 * Internal Health Management *
	 ******************************/

	/**
	 * Check file health and update state accordingly
	 *
	 * Validates:
	 * - Upload error is OK
	 * - File reference exists
	 * - File is still valid (not externally deleted)
	 *
	 * Updates error code and nulls file if problems detected.
	 *
	 * @return bool True if file is healthy
	 */
	private function checkHealth(): bool {
		// Already failed upload
		if ($this->error !== UPLOAD_ERR_OK) {
			return false;
		}

		// File is null (shouldn't happen with OK status)
		if ($this->file === null) {
			$this->error = self::ERROR_FILE_DELETED;
			return false;
		}

		// File was deleted/invalidated externally
		if (!$this->file->isValid()) {
			$this->error = self::ERROR_FILE_DELETED;
			$this->file  = null;
			return false;
		}

		return true;
	}

	/**
	 * Get human-readable error message
	 *
	 * @return string Error description
	 */
	private function getErrorMessage(): string {
		return match($this->error) {
			UPLOAD_ERR_INI_SIZE      => 'File exceeds upload_max_filesize',
			UPLOAD_ERR_FORM_SIZE     => 'File exceeds MAX_FILE_SIZE',
			UPLOAD_ERR_PARTIAL       => 'File partially uploaded',
			UPLOAD_ERR_NO_FILE       => 'No file uploaded',
			UPLOAD_ERR_NO_TMP_DIR    => 'Missing temp directory',
			UPLOAD_ERR_CANT_WRITE    => 'Failed to write file',
			UPLOAD_ERR_EXTENSION     => 'Upload stopped by extension',
			self::ERROR_FILE_DELETED => 'File was deleted or invalidated',
			default                  => 'Unknown error',
		};
	}
}
