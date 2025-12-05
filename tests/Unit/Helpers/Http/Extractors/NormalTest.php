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

namespace Peku\Tests\Unit\Helpers\Http\Extractors;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Peku\Helpers\Http\Extractors\Normal;
use Peku\Helpers\Http\UploadedFile;

/**
 * Unit tests for Normal extractor
 *
 * Tests extraction from superglobals via public API.
 * Tests without mocks first, then mock-dependent tests.
 */
class NormalTest extends TestCase {

	use PHPMock;

	private array $tempFiles = [];

	protected function setUp(): void {
		$_GET    = [];
		$_POST   = [];
		$_FILES  = [];
		$_SERVER = [];
	}

	protected function tearDown(): void {
		foreach ($this->tempFiles as $file) {
			@unlink($file);
		}
		$this->tempFiles = [];
	}

	private function createTempFile(string $content = 'test'): string {
		$path = sys_get_temp_dir() . '/peku_test_' . uniqid() . '.txt';
		file_put_contents($path, $content);
		$this->tempFiles[] = $path;
		return $path;
	}

	// ========================================================================
	// GET/POST Extraction Tests
	// ========================================================================

	public function testExtractsGetParameters(): void {
		$data = ['name' => 'John', 'age' => '25', 'active' => 'true'];
		$_GET = $data;
		$extractor = new Normal();
		$this->assertSame($data, $extractor->getQuery());
	}

	public function testExtractsPostParameters(): void {
		$data = ['email' => 'john@example.com', 'password' => 'secret123'];
		$_POST = $data;
		$extractor = new Normal();
		$this->assertSame($data, $extractor->getData());
	}

	public function testExtractsEmptySuperglobals(): void {
		$extractor = new Normal();
		$this->assertSame([], $extractor->getQuery());
		$this->assertSame([], $extractor->getData());
		$this->assertSame([], $extractor->getFiles());
	}

	public function testExtractsServerVariables(): void {
		$data = [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/test',
			'HTTP_HOST'      => 'example.com',
		];
		$_SERVER = $data;
		$extractor = new Normal();
		$this->assertSame($data, $extractor->getServer());
	}

	public function testClearsSuperglobalsAfterExtraction(): void {
		$_GET    = ['test' => 'data'];
		$_POST   = ['foo' => 'bar'];
		$_SERVER = ['REQUEST_METHOD' => 'GET'];
		$_FILES  = [
			'large_file' => [
				'name'      => 'huge.zip',
				'type'      => 'application/zip',
				'tmp_name'  => '',
				'error'     => UPLOAD_ERR_INI_SIZE,
				'size'      => 0,
				'full_path' => 'huge.zip',
			],
		];
		new Normal();

		// Verify superglobals were cleared for security
		$this->assertSame([], $_GET);
		$this->assertSame([], $_POST);
		$this->assertSame([], $_SERVER);
		$this->assertSame([], $_FILES);
	}

	// ========================================================================
	// Upload Error Handling Tests (No Mocks Needed)
	// ========================================================================

	public function testHandlesUploadError(): void {
		$_FILES = [
			'large_file' => [
				'name'      => 'huge.zip',
				'type'      => 'application/zip',
				'tmp_name'  => '',
				'error'     => UPLOAD_ERR_INI_SIZE,
				'size'      => 0,
				'full_path' => 'huge.zip',
			],
		];
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		/** @var UploadedFile $upload */
		$upload = $files['large_file'];
		$this->assertInstanceOf(UploadedFile::class, $upload);
		$this->assertFalse($upload->isAvailable());
		$this->assertTrue($upload->hasError());
		$this->assertSame(UPLOAD_ERR_INI_SIZE, $upload->getError());
		$this->assertNull($upload->getFile());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testHandlesMultipleFilesWithMixedErrors(): void {
		$tempFile = $this->createTempFile('ok');
		$_FILES = [
			'mixed' => [
				'name'      => ['good.txt', 'bad.txt'],
				'type'      => ['text/plain', 'text/plain'],
				'tmp_name'  => [$tempFile, ''],
				'error'     => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
				'size'      => [100, 0],
				'full_path' => ['good.txt', 'bad.txt'],
			],
		];
		$isUploadedFile = $this->getFunctionMock('Peku\\Helpers\\Http\\Extractors', 'is_uploaded_file');
		$isUploadedFile->expects($this->once())->willReturn(true);
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertCount(2, $files['mixed']);
		$this->assertSame(UPLOAD_ERR_OK, $files['mixed'][0]->getError());
		$this->assertSame(UPLOAD_ERR_NO_FILE, $files['mixed'][1]->getError());
		$this->assertFalse($files['mixed'][1]->isAvailable());
	}

	// ========================================================================
	// Edge Cases (No Mocks Needed)
	// ========================================================================

	public function testSkipsMalformedFileEntry(): void {
		$_FILES = [
			'valid'   => [
				'name'      => 'test.txt',
				'type'      => 'text/plain',
				'tmp_name'  => '',
				'error'     => UPLOAD_ERR_NO_FILE,
				'size'      => 0,
				'full_path' => 'test.txt',
			],
			'invalid' => [
				'name'     => 'bad.txt',
				'tmp_name' => '',
			],
		];
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertArrayHasKey('valid', $files);
		$this->assertArrayNotHasKey('invalid', $files);
	}

	public function testHandlesEmptyFilesArray(): void {
		$_FILES = [];
		$extractor = new Normal();
		$this->assertSame([], $extractor->getFiles());
	}

	public function testFallsBackToFilenameWhenFullPathMissing(): void {
		$_FILES = [
			'old_php' => [
				'name'     => 'legacy.txt',
				'type'     => 'text/plain',
				'tmp_name' => '',
				'error'    => UPLOAD_ERR_NO_FILE,
				'size'     => 0,
			],
		];
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertSame('legacy.txt', $files['old_php']->getClientPath());
	}

	// ========================================================================
	// Single File Upload Tests (Require Mocks)
	// ========================================================================

	/**
	 * @runInSeparateProcess
	 */
	public function testExtractsSingleFileUpload(): void {
		$tempFile = $this->createTempFile('file content');
		$_FILES = [
			'avatar' => [
				'name'      => 'photo.jpg',
				'type'      => 'image/jpeg',
				'tmp_name'  => $tempFile,
				'error'     => UPLOAD_ERR_OK,
				'size'      => 1024,
				'full_path' => 'photo.jpg',
			],
		];
		$isUploadedFile = $this->getFunctionMock('Peku\\Helpers\\Http\\Extractors', 'is_uploaded_file');
		$isUploadedFile->expects($this->once())->willReturn(true);
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertCount(1, $files);
		$this->assertArrayHasKey('avatar', $files);
		$this->assertInstanceOf(UploadedFile::class, $files['avatar']);
		/** @var UploadedFile $upload */
		$upload = $files['avatar'];
		$this->assertTrue($upload->isAvailable());
		$this->assertSame('photo.jpg', $upload->getClientFilename());
		$this->assertSame('photo.jpg', $upload->getClientPath());
		$this->assertSame(UPLOAD_ERR_OK, $upload->getError());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testExtractsSingleFileWithFullPath(): void {
		$tempFile = $this->createTempFile('content');
		$_FILES = [
			'document' => [
				'name'      => 'File.pdf',
				'type'      => 'application/pdf',
				'tmp_name'  => $tempFile,
				'error'     => UPLOAD_ERR_OK,
				'size'      => 2048,
				'full_path' => 'project/docs/File.pdf',
			],
		];
		$isUploadedFile = $this->getFunctionMock('Peku\\Helpers\\Http\\Extractors', 'is_uploaded_file');
		$isUploadedFile->expects($this->once())->willReturn(true);
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertSame('File.pdf', $files['document']->getClientFilename());
		$this->assertSame('project/docs/File.pdf', $files['document']->getClientPath());
	}

	// ========================================================================
	// Multiple File Upload Tests (Require Mocks)
	// ========================================================================

	/**
	 * @runInSeparateProcess
	 */
	public function testExtractsMultipleFileUploads(): void {
		$tempFile1 = $this->createTempFile('file1');
		$tempFile2 = $this->createTempFile('file2');
		$_FILES = [
			'docs' => [
				'name'      => ['doc1.txt', 'doc2.txt'],
				'type'      => ['text/plain', 'text/plain'],
				'tmp_name'  => [$tempFile1, $tempFile2],
				'error'     => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
				'size'      => [100, 200],
				'full_path' => ['doc1.txt', 'doc2.txt'],
			],
		];
		$isUploadedFile = $this->getFunctionMock('Peku\\Helpers\\Http\\Extractors', 'is_uploaded_file');
		$isUploadedFile->expects($this->exactly(2))->willReturn(true);
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertArrayHasKey('docs', $files);
		$this->assertIsArray($files['docs']);
		$this->assertCount(2, $files['docs']);
		$this->assertInstanceOf(UploadedFile::class, $files['docs'][0]);
		$this->assertSame('doc1.txt', $files['docs'][0]->getClientFilename());
		$this->assertTrue($files['docs'][0]->isAvailable());
		$this->assertInstanceOf(UploadedFile::class, $files['docs'][1]);
		$this->assertSame('doc2.txt', $files['docs'][1]->getClientFilename());
		$this->assertTrue($files['docs'][1]->isAvailable());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testHandlesSecurityCheckFailure(): void {
		$tempFile = $this->createTempFile('malicious');
		$_FILES = [
			'suspicious' => [
				'name'      => 'hack.php',
				'type'      => 'application/x-php',
				'tmp_name'  => $tempFile,
				'error'     => UPLOAD_ERR_OK,
				'size'      => 512,
				'full_path' => 'hack.php',
			],
		];
		$isUploadedFile = $this->getFunctionMock('Peku\\Helpers\\Http\\Extractors', 'is_uploaded_file');
		$isUploadedFile->expects($this->once())->willReturn(false);
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertFalse($files['suspicious']->isAvailable());
		$this->assertTrue($files['suspicious']->hasError());
		$this->assertSame(UPLOAD_ERR_CANT_WRITE, $files['suspicious']->getError());
		$this->assertNull($files['suspicious']->getFile());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testHandlesFileManageException(): void {
		$_FILES = [
			'doc' => [
				'name'      => 'test.txt',
				'type'      => 'text/plain',
				'tmp_name'  => '/nonexistent/invalid/path.txt',
				'error'     => UPLOAD_ERR_OK,
				'size'      => 100,
				'full_path' => 'test.txt',
			],
		];
		$isUploadedFile = $this->getFunctionMock('Peku\\Helpers\\Http\\Extractors', 'is_uploaded_file');
		$isUploadedFile->expects($this->once())->willReturn(true);
		$extractor = new Normal();
		$files     = $extractor->getFiles();
		$this->assertSame(UPLOAD_ERR_CANT_WRITE, $files['doc']->getError());
		$this->assertFalse($files['doc']->isAvailable());
	}
}
