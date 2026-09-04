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

namespace Peku\Tests\Unit\Helpers\Http;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Files\{FileInterface, FileException};
use Peku\Helpers\Http\{UploadedFile, UploadException};

/**
 * Unit tests for UploadedFile wrapper
 *
 * Tests upload metadata, health tracking, and deployment operations.
 */
class UploadedFileTest extends TestCase {

	private string $tempDir;
	private array $tempFiles = [];

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/peku_upload_test_' . uniqid();
		mkdir($this->tempDir, 0755, true);
	}

	protected function tearDown(): void {
		// Clean up
		foreach ($this->tempFiles as $file) {
			@unlink($file);
		}
		if (is_dir($this->tempDir)) {
			$files = glob($this->tempDir . '/*');
			foreach ($files as $file) {
				@unlink($file);
			}
			@rmdir($this->tempDir);
		}
	}

	private function createMockFile(bool $isValid = true): FileInterface {
		$mock = $this->createMock(FileInterface::class);
		$mock->method('isValid')->willReturn($isValid);
		return $mock;
	}

	/***********************************
	 * Constructor & Basic State Tests *
	 ***********************************/

	public function testConstructorWithSuccessfulUpload(): void {
		$file   = $this->createMockFile(true);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertTrue($upload->isAvailable());
		$this->assertFalse($upload->hasError());
		$this->assertSame(UPLOAD_ERR_OK, $upload->getError());
	}

	public function testConstructorWithFailedUpload(): void {
		$upload = new UploadedFile(null, UPLOAD_ERR_INI_SIZE, 'large.zip', 'large.zip');

		$this->assertFalse($upload->isAvailable());
		$this->assertTrue($upload->hasError());
		$this->assertSame(UPLOAD_ERR_INI_SIZE, $upload->getError());
	}

	public function testConstructorWithVariousErrorCodes(): void {
		$errors = [
			UPLOAD_ERR_FORM_SIZE,
			UPLOAD_ERR_PARTIAL,
			UPLOAD_ERR_NO_FILE,
			UPLOAD_ERR_NO_TMP_DIR,
			UPLOAD_ERR_CANT_WRITE,
			UPLOAD_ERR_EXTENSION,
		];

		foreach ($errors as $errorCode) {
			$upload = new UploadedFile(null, $errorCode, 'file.txt', 'file.txt');
			$this->assertTrue($upload->hasError());
			$this->assertSame($errorCode, $upload->getError());
		}
	}

	/*************************
	 * Metadata Access Tests *
	 *************************/

	public function testGetClientFilename(): void {
		$file   = $this->createMockFile();
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'document.pdf', 'path/document.pdf');

		$this->assertSame('document.pdf', $upload->getClientFilename());
	}

	public function testGetClientPath(): void {
		$file   = $this->createMockFile();
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'File.php', 'project/src/File.php');

		$this->assertSame('project/src/File.php', $upload->getClientPath());
	}

	public function testGetClientPathDefaultsToFilename(): void {
		$file   = $this->createMockFile();
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'simple.txt', '');

		// Empty path should default to filename
		$this->assertSame('simple.txt', $upload->getClientPath());
	}

	public function testMetadataAccessAlwaysSafe(): void {
		// Metadata should be accessible even with failed uploads
		$upload = new UploadedFile(null, UPLOAD_ERR_NO_FILE, 'test.txt', 'dir/test.txt');

		$this->assertSame('test.txt', $upload->getClientFilename());
		$this->assertSame('dir/test.txt', $upload->getClientPath());
	}

	/*************************
	 * Health Checking Tests *
	 *************************/

	public function testIsAvailableReturnsTrueForHealthyFile(): void {
		$file   = $this->createMockFile(true);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertTrue($upload->isAvailable());
	}

	public function testIsAvailableReturnsFalseWithUploadError(): void {
		$file   = $this->createMockFile(true);
		$upload = new UploadedFile($file, UPLOAD_ERR_PARTIAL, 'test.txt', 'test.txt');

		$this->assertFalse($upload->isAvailable());
	}

	public function testIsAvailableReturnsFalseWhenFileIsNull(): void {
		$upload = new UploadedFile(null, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		// Inconsistent state: OK error but null file
		$this->assertFalse($upload->isAvailable());
	}

	public function testIsAvailableDetectsExternalFileDeletion(): void {
		$file = $this->createMockFile(true);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertTrue($upload->isAvailable());

		// Simulate external file deletion
		$file = $this->createMockFile(false);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertFalse($upload->isAvailable());
		$this->assertTrue($upload->hasError());
		$this->assertSame(100, $upload->getError()); // ERROR_FILE_DELETED
	}

	/*******************
	 * getFile() Tests *
	 *******************/

	public function testGetFileReturnsFileWhenAvailable(): void {
		$file   = $this->createMockFile(true);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertSame($file, $upload->getFile());
	}

	public function testGetFileReturnsNullWhenUnavailable(): void {
		$upload = new UploadedFile(null, UPLOAD_ERR_NO_FILE, 'test.txt', 'test.txt');

		$this->assertNull($upload->getFile());
	}

	public function testGetFileReturnsNullAfterExternalDeletion(): void {
		$invalidFile = $this->createMockFile(false);
		$upload      = new UploadedFile($invalidFile, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertNull($upload->getFile());
	}

	/**************************
	 * moveTo() Tests - Basic *
	 **************************/

	public function testMoveToThrowsWhenFileUnavailable(): void {
		$upload = new UploadedFile(null, UPLOAD_ERR_NO_FILE, 'test.txt', 'test.txt');

		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('Cannot move file');

		$upload->moveTo($this->tempDir);
	}

	public function testMoveToWithSimpleFilename(): void {
		$tempFile = $this->tempDir . '/source.txt';
		file_put_contents($tempFile, 'content');
		$this->tempFiles[] = $tempFile;

		$file = $this->createMock(FileInterface::class);
		$file->method('isValid')->willReturn(true);
		$file->expects($this->once())
		->method('move')
		->with($this->tempDir . '/uploaded.txt');

		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'uploaded.txt', 'uploaded.txt');

		$upload->moveTo($this->tempDir);
	}

	public function testMoveToPreservesClientPath(): void {
		$file = $this->createMock(FileInterface::class);
		$file->method('isValid')->willReturn(true);
		$file->expects($this->once())
		->method('move')
		->with($this->tempDir . '/project/src/File.php');

		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'File.php', 'project/src/File.php');

		$upload->moveTo($this->tempDir);
	}

	public function testMoveToCreatesDirectoryStructure(): void {
		$file = $this->createMock(FileInterface::class);
		$file->method('isValid')->willReturn(true);
		$file->method('move')->willReturnCallback(function($dest) {
			// Verify directory was created
			$this->assertDirectoryExists(dirname($dest));
		});

			$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'file.txt', 'deep/nested/path/file.txt');

			$upload->moveTo($this->tempDir);
	}

	public function testMoveToHandlesTrailingSlash(): void {
		$file = $this->createMock(FileInterface::class);
		$file->method('isValid')->willReturn(true);
		$file->expects($this->once())
		->method('move')
		->with($this->tempDir . '/test.txt');

		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		// Should work with or without trailing slash
		$upload->moveTo($this->tempDir . '/');
	}

	public function testMoveToThrowsOnMkdirFailure(): void {
		$readonlyDir = $this->tempDir . '/readonly';
		mkdir($readonlyDir, 0555); // Read-only

		$file = $this->createMock(FileInterface::class);
		$file->method('isValid')->willReturn(true);

		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'file.txt', 'subdir/file.txt');

		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('Failed to create directory');

		try {
			$upload->moveTo($readonlyDir);
		}
		finally {
			chmod($readonlyDir, 0755);
			@rmdir($readonlyDir);
		}
	}

	public function testMoveToThrowsOnFileMoveFail(): void {
		$file = $this->createMock(FileInterface::class);
		$file->method('isValid')->willReturn(true);
		$file->method('move')->willThrowException(
			new FileException('test.txt', 'Move failed')
			);

		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('Failed to move uploaded file');

		$upload->moveTo($this->tempDir);
	}

	/****************************
	 * Health State Transitions *
	 ****************************/

	public function testHealthCheckUpdatesErrorState(): void {
		$file = $this->createMockFile(false);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		// First call to isAvailable() detects invalid file
		$this->assertFalse($upload->isAvailable());

		// Error code should be updated
		$this->assertSame(100, $upload->getError()); // ERROR_FILE_DELETED

		// File should be null now
		$this->assertNull($upload->getFile());
	}

	public function testHealthCheckNullsFileReference(): void {
		$file = $this->createMockFile(false);
		$upload = new UploadedFile($file, UPLOAD_ERR_OK, 'test.txt', 'test.txt');

		$this->assertFalse($upload->isAvailable());
		$this->assertNull($upload->getFile());
	}

	/***********************
	 * Error Message Tests *
	 ***********************/

	public function testMoveToThrowsWithNoTmpDirMessage(): void {
		$file = new UploadedFile(null, UPLOAD_ERR_NO_TMP_DIR, 'test.txt');
		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('Missing temp directory');
		$file->moveTo($this->tempDir);
	}

	public function testMoveToThrowsWithCantWriteMessage(): void {
		$file = new UploadedFile(null, UPLOAD_ERR_CANT_WRITE, 'test.txt');
		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('Failed to write file');
		$file->moveTo($this->tempDir);
	}

	public function testMoveToThrowsWithExtensionMessage(): void {
		$file = new UploadedFile(null, UPLOAD_ERR_EXTENSION, 'test.txt');
		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('Upload stopped by extension');
		$file->moveTo($this->tempDir);
	}

	public function testMoveToThrowsWithFileDeletedMessage(): void {
		$file = new UploadedFile(null, 100, 'test.txt'); // ERROR_FILE_DELETED = 100
		$this->expectException(UploadException::class);
		$this->expectExceptionMessage('File was deleted or invalidated');
		$file->moveTo($this->tempDir);
	}
}
