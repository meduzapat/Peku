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

namespace Peku\Tests\Unit\Helpers\Files;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Files\File;
use Peku\Helpers\Files\FileException;

/**
 * Unit tests for File class
 */
class FileTest extends TestCase {

	private string $tempDir;
	private array $tempFiles = [];

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/peku_test_' . uniqid();
		mkdir($this->tempDir, 0755, true);
	}

	protected function tearDown(): void {
		// Clean up temp directory and all files
		if (is_dir($this->tempDir)) {
			$files = glob($this->tempDir . '/*');
			foreach ($files as $file) {
				@unlink($file);
			}
			@rmdir($this->tempDir);
		}
	}

	private function createTempFile(string $name = 'test.txt', string $contents = 'test content'): string {
		$path = $this->tempDir . '/' . $name;
		file_put_contents($path, $contents);
		$this->tempFiles[] = $path;
		return $path;
	}

	// ========================================================================
	// Factory & Registry Tests
	// ========================================================================

	public function testManageReturnsFileInstance(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$this->assertInstanceOf(File::class, $file);
		$this->assertSame(realpath($path), $file->getPath());
	}

	public function testManageReturnsSameInstanceForSamePath(): void {
		$path = $this->createTempFile();

		$file1 = File::manage($path);
		$file2 = File::manage($path);

		$this->assertSame($file1, $file2);
	}

	public function testManageThrowsIfFileDoesNotExist(): void {
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Cannot resolve path');
		File::manage('/nonexistent/file.txt');
	}

	public function testManageThrowsIfPathIsDirectory(): void {
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Path is not a file');
		File::manage($this->tempDir);
	}

	public function testIsManaged(): void {

		$path = $this->createTempFile();

		$this->assertFalse(File::isManaged($path));

		File::manage($path);

		$this->assertTrue(File::isManaged($path));
		$this->assertFalse(File::isManaged('invalidpath'));
	}

	// ========================================================================
	// Read Operations Tests
	// ========================================================================

	public function testGetPath(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$this->assertSame(realpath($path), $file->getPath());
	}

	public function testGetName(): void {
		$path = $this->createTempFile('myfile.txt');
		$file = File::manage($path);

		$this->assertSame('myfile.txt', $file->getName());
	}

	public function testGetSize(): void {
		$contents = 'Hello World!';
		$path = $this->createTempFile('test.txt', $contents);
		$file = File::manage($path);

		$this->assertSame(strlen($contents), $file->getSize());
	}

	public function testGetSizeWithEmptyFile(): void {
		$path = $this->createTempFile('empty.txt', '');
		$file = File::manage($path);

		$this->assertSame(0, $file->getSize());
	}

	public function testGetMimeType(): void {
		$path = $this->createTempFile('test.txt', 'text content');
		$file = File::manage($path);
		$mime = $file->getMimeType();

		$this->assertStringContainsString('text', $mime);
	}

	public function testGetContents(): void {
		$contents = 'Test file contents';
		$path = $this->createTempFile('test.txt', $contents);
		$file = File::manage($path);

		$this->assertSame($contents, $file->getContents());
	}

	// ========================================================================
	// Validation Tests
	// ========================================================================

	public function testIsValidReturnsTrueForExistingFile(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$this->assertTrue($file->isValid());
	}

	public function testIsValidReturnsFalseAfterExternalDeletion(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		unlink($path);

		$this->assertFalse($file->isValid());
	}

	public function testIsValidReturnsFalseAfterDelete(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->delete();

		$this->assertFalse($file->isValid());
	}

	// ========================================================================
	// Copy Tests
	// ========================================================================

	public function testCopyCreatesNewFile(): void {
		$path = $this->createTempFile('original.txt', 'original content');
		$dest = $this->tempDir . '/copy.txt';

		$file = File::manage($path);
		$copy = $file->copy($dest);

		$this->tempFiles[] = $dest;

		$this->assertFileExists($dest);
		$this->assertNotSame($file, $copy);
		$this->assertSame('original content', $copy->getContents());
	}

	public function testCopyPreservesOriginal(): void {
		$path = $this->createTempFile('original.txt', 'original content');
		$dest = $this->tempDir . '/copy.txt';

		$file = File::manage($path);
		$file->copy($dest);

		$this->tempFiles[] = $dest;

		$this->assertTrue($file->isValid());
		$this->assertFileExists($path);
		$this->assertSame('original content', $file->getContents());
	}

	// ========================================================================
	// Move Tests
	// ========================================================================

	public function testMoveUpdatesPath(): void {
		$oldPath = $this->createTempFile('old.txt');
		$newPath = $this->tempDir . '/new.txt';

		$file = File::manage($oldPath);
		$file->move($newPath);

		$this->tempFiles[] = $newPath;

		$this->assertSame(realpath($newPath), $file->getPath());
		$this->assertFileDoesNotExist($oldPath);
		$this->assertFileExists($newPath);
	}

	public function testMoveWithDifferentFilename(): void {
		$oldPath = $this->createTempFile('original.txt', 'content');
		$newPath = $this->tempDir . '/renamed.txt';

		$file = File::manage($oldPath);
		$file->move($newPath);

		$this->tempFiles[] = $newPath;

		$this->assertSame('renamed.txt', $file->getName());
		$this->assertSame('content', $file->getContents());
	}

	public function testMoveUpdatesRegistry(): void {
		$oldPath = $this->createTempFile('old.txt');
		$newPath = $this->tempDir . '/new.txt';

		$file = File::manage($oldPath);
		$file->move($newPath);

		$this->tempFiles[] = $newPath;

		$this->assertFalse(File::isManaged($oldPath));
		$this->assertTrue(File::isManaged($newPath));
	}

	public function testMoveThrowsIfDestinationExists(): void {
		$source = $this->createTempFile('source.txt');
		$dest = $this->createTempFile('dest.txt');

		$file = File::manage($source);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Destination file already exists');

		$file->move($dest);
	}

	public function testMoveThrowsIfDestinationDirDoesNotExist(): void {
		$source = $this->createTempFile('source.txt');
		$dest = '/nonexistent/directory/file.txt';

		$file = File::manage($source);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Destination directory does not exist');

		$file->move($dest);
	}

	// ========================================================================
	// Delete Tests
	// ========================================================================

	public function testDeleteRemovesFile(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->delete();

		$this->assertFileDoesNotExist($path);
	}

	public function testDeleteInvalidatesInstance(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->delete();

		$this->assertFalse($file->isValid());
	}

	public function testDeleteRemovesFromRegistry(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->delete();

		$this->assertFalse(File::isManaged($path));
	}

	public function testGetPathThrowsAfterDelete(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->delete();

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('File has been invalidated');

		$file->getPath();
	}

	public function testOperationsThrowAfterDelete(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->delete();

		$this->expectException(FileException::class);
		$file->getSize();
	}

	// ========================================================================
	// Delete/Recreate Tests
	// ========================================================================

	public function testDeleteAndRecreateProduceDifferentInstances(): void {
		$path = $this->tempDir . '/file.txt';
		file_put_contents($path, 'first');
		$this->tempFiles[] = $path;

		$file1 = File::manage($path);
		$file1->delete();

		file_put_contents($path, 'second');
		$file2 = File::manage($path);

		$this->assertNotSame($file1, $file2);
		$this->assertFalse($file1->isValid());
		$this->assertTrue($file2->isValid());
		$this->assertSame('second', $file2->getContents());
	}

	// ========================================================================
	// External Deletion Tests
	// ========================================================================

	public function testInvalidateIfGoneOnRead(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		unlink($path);

		$this->expectException(FileException::class);
		$file->getSize();

		$this->assertFalse($file->isValid());
	}

	public function testInvalidateIfGoneOnOperation(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		unlink($path);

		try {
			$file->copy($this->tempDir . '/copy.txt');
			$this->fail('Expected FileException');
		}
		catch (FileException $e) {
			// Expected
		}

		$this->assertFalse($file->isValid());
	}

	// ========================================================================
	// Streaming Tests
	// ========================================================================

	public function testOpen(): void {
		$path = $this->createTempFile('test.txt', 'content');
		$file = File::manage($path);

		$handle = $file->open('r');

		$this->assertIsResource($handle);
		fclose($handle);
	}

	public function testReadChunked(): void {
		$content = str_repeat('A', 1000);
		$path = $this->createTempFile('large.txt', $content);
		$file = File::manage($path);

		$result = '';
		$file->readChunked(
			function($chunk) use (&$result) { $result .= $chunk;},
			100
		);

		$this->assertSame($content, $result);
	}

	public function testCopyToStream(): void {
		$content = 'stream content';
		$path = $this->createTempFile('source.txt', $content);
		$file = File::manage($path);

		$dest = fopen('php://memory', 'w+');
		$file->copyToStream($dest);

		rewind($dest);
		$result = stream_get_contents($dest);
		fclose($dest);

		$this->assertSame($content, $result);
	}

	public function testCopyToStreamFailsBadStream(): void {
		$path = $this->createTempFile('source.txt', 'data');
		$file = File::manage($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Destination must be a valid stream resource');
		$file->copyToStream(null);
	}

	public function testCopyToStreamFailsCopy(): void {
		$path = $this->createTempFile('source.txt', 'data');
		$file = File::manage($path);

		// Open destination as read-only (cannot be written into)
		$dest = fopen('php://memory', 'r');

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to copy stream');

		$file->copyToStream($dest);
	}

	// ========================================================================
	// Permissions Tests
	// ========================================================================

	public function testSetAndGetPermissions(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(0644);

		$this->assertSame(0644, $file->getPermissions());
	}

	public function testSetPermissionsWithString(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		$file->setPermissions('rw-r--r--');
		$this->assertSame(0644, $file->getPermissions());
		// Test all dashes (no permissions)
		$file->setPermissions('---------');
		$this->assertSame(0000, $file->getPermissions());
	}

	public function testSetPermissionsWithPresetPrivate(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(File::PRIVATE);

		$this->assertSame(0600, $file->getPermissions());
	}

	public function testSetPermissionsWithPresetShared(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(File::SHARED);

		$this->assertSame(0644, $file->getPermissions());
	}

	public function testSetPermissionsWithPresetExecutable(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(File::EXECUTABLE);

		$this->assertSame(0755, $file->getPermissions());
	}

	public function testSetPermissionsWithPresetPublic(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(File::PUBLIC);

		$this->assertSame(0666, $file->getPermissions());
	}

	public function testSetPermissionsThrowsOnInvalidString(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Permission string must be 9 characters');

		$file->setPermissions('invalid');
	}

	public function testSetPermissionsWithInvalidCharacters(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Permission string must be 9 characters');
		$file->setPermissions('rwxrwxrwxr'); // 10 chars
	}

	public function testGetPermissionsString(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(0644);

		$this->assertSame('rw-r--r--', $file->getPermissionsString());
	}

	public function testIsReadable(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(0644);

		$this->assertTrue($file->isReadable());
	}

	public function testIsWritable(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(0644);

		$this->assertTrue($file->isWritable());
	}

	public function testIsExecutable(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$file->setPermissions(0755);

		$this->assertTrue($file->isExecutable());
	}

	public function testGetPermissionsThrows(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		unlink($path);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get file permissions');

		$file->getPermissions();
	}

	public function testSetPermissionsThrows(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		unlink($path);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to set permissions to');

		$file->setPermissions(0755);
	}

	// ========================================================================
	// File Utility Tests (create, write, append)
	// ========================================================================

	public function testFileCreate(): void {
		$path = $this->tempDir . '/new.txt';
		$content = 'new content';

		$file = File::create($path, $content);

		$this->tempFiles[] = $path;

		$this->assertFileExists($path);
		$this->assertSame($content, $file->getContents());
	}

	public function testFileCreateThrowsIfExists(): void {
		$path = $this->createTempFile();

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('File already exists');

		File::create($path);
	}

	public function testFileCreateTemp(): void {
		$file = File::createTemp('test_');

		$this->tempFiles[] = $file->getPath();

		$this->assertFileExists($file->getPath());
		$this->assertStringContainsString('test_', $file->getName());
	}

	public function testFileWrite(): void {
		$path = $this->createTempFile('test.txt', 'old');
		$file = File::manage($path);

		$file->write('new content');

		$this->assertSame('new content', $file->getContents());
	}

	public function testFileAppend(): void {
		$path = $this->createTempFile('test.txt', 'first');
		$file = File::manage($path);

		$file->append(' second');

		$this->assertSame('first second', $file->getContents());
	}

	// ========================================================================
	// Metadata Tests
	// ========================================================================

	public function testGetMTime(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$mtime = $file->getMTime();

		$this->assertIsInt($mtime);
		$this->assertGreaterThan(0, $mtime);
	}

	public function testGetMTimeError(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		// delete file
		@unlink($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get modification time');

		$file->getMTime();
	}

	public function testGetATime(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$atime = $file->getATime();

		$this->assertIsInt($atime);
		$this->assertGreaterThan(0, $atime);
	}

	public function testGetATimeThrowsOnInaccessibleFile(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		// delete file
		@unlink($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get access time');

		$file->getATime();
	}

	public function testGetCTime(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$ctime = $file->getCTime();

		$this->assertIsInt($ctime);
		$this->assertGreaterThan(0, $ctime);
	}

	public function testGetCTimeThrowsOnInaccessibleFile(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		// delete file
		@unlink($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get change time');

		$file->getCTime();
	}

	public function testTouch(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$oldMtime = $file->getMTime();
		sleep(1);

		$file->touch();
		$newMtime = $file->getMTime();

		$this->assertGreaterThan($oldMtime, $newMtime);
	}

	public function testTouchWithCustomTime(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);

		$customTime = strtotime('2020-01-01 00:00:00');
		$file->touch($customTime, $customTime);

		$this->assertSame($customTime, $file->getMTime());
		$this->assertSame($customTime, $file->getATime());
	}

	// ========================================================================
	// Ownership Tests (may require privileges)
	// ========================================================================

	public function testGetOwner(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		$owner = $file->getOwner();
		$this->assertSame($owner, get_current_user());
	}

	public function testGetOwnerThrow(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		@unlink($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get file owner');

		$file->getOwner();
	}

	public function testSetOwner(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		$owner = $file->getOwner();
		$file->setOwner(get_current_user());
		$this->assertSame($owner, get_current_user());
	}

	public function testSetOwnerThrow(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to set owner to');
		$file->setOwner('root');
	}

	public function testGetGroup(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		$gid       = @posix_getegid();
		$groupInfo = @posix_getgrgid($gid);
		$expected  = $groupInfo['name'] ?? '';
		$group = $file->getGroup();
		$this->assertSame($expected, $group);
	}

	public function testGetGroupThrow(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		@unlink($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get file group');

		$file->getGroup();
	}

	public function testSetGroup(): void {
		$path  = $this->createTempFile();
		$file  = File::manage($path);
		$group = $file->getGroup();
		$gid       = @posix_getegid();
		$groupInfo = @posix_getgrgid($gid);
		$expected  = $groupInfo['name'] ?? '';
		$file->setGroup($group);
		$this->assertSame($expected, $group);
	}

	public function testSetGroupThrow(): void {
		$path = $this->createTempFile();
		$file = File::manage($path);
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to set group to');
		$file->setGroup('root');
	}

	// ========================================================================
	// Permission Edge Cases
	// ========================================================================

	public function testCreateInNonWritableDirectory(): void {
		$dir = $this->tempDir . '/readonly';
		mkdir($dir, 0755);
		chmod($dir, 0555);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to create file');

		try {
			File::create($dir . '/test.txt', 'content');
		}
		finally {
			chmod($dir, 0755);
			@rmdir($dir);
		}
	}

	public function testGetContentsOnNonReadableFile(): void {
		$path = $this->createTempFile('secret.txt', 'secret data');
		$file = File::manage($path);

		// Remove read permission
		chmod($path, 0000);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to read file');

		try {
			$file->getContents();
		}
		finally {
			chmod($path, 0644);
		}
	}

	public function testWriteToNonWritableFile(): void {
		$path = $this->createTempFile('readonly.txt', 'original');
		$file = File::manage($path);

		// Remove write permission
		chmod($path, 0444);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to write file');

		try {
			$file->write('new content');
		}
		finally {
			chmod($path, 0644);
		}
	}

	public function testAppendToNonWritableFile(): void {
		$path = $this->createTempFile('readonly.txt', 'original');
		$file = File::manage($path);

		// Remove write permission
		chmod($path, 0444);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to append to file');

		try {
			$file->append(' more');
		}
		finally {
			chmod($path, 0644);
		}
	}

	public function testCopyFromNonReadableFile(): void {
		$source = $this->createTempFile('secret.txt', 'secret');
		$dest = $this->tempDir . '/copy.txt';
		$file = File::manage($source);

		// Remove read permission
		chmod($source, 0000);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to copy file');

		try {
			$file->copy($dest);
		}
		finally {
			chmod($source, 0644);
		}
	}

	public function testMoveFromNonWritableDirectory(): void {
		$dir = $this->tempDir . '/readonly';
		mkdir($dir, 0755);

		$source = $dir . '/file.txt';
		file_put_contents($source, 'content');
		$this->tempFiles[] = $source;

		$file = File::manage($source);

		// Make directory read-only (can't delete/move files)
		chmod($dir, 0555);

		$dest = $this->tempDir . '/moved.txt';

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to move file');

		try {
			$file->move($dest);
		}
		finally {
			chmod($dir, 0755);
		}
	}

	public function testDeleteNonWritableFile(): void {
		$dir = $this->tempDir . '/readonly';
		mkdir($dir, 0755);

		$path = $dir . '/file.txt';
		file_put_contents($path, 'content');
		$this->tempFiles[] = $path;

		$file = File::manage($path);

		// Make directory read-only (can't delete files)
		chmod($dir, 0555);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to delete file');

		try {
			$file->delete();
		}
		finally {
			chmod($dir, 0755);
		}
	}

	public function testOpenNonReadableFileForReading(): void {
		$path = $this->createTempFile('secret.txt', 'secret');
		$file = File::manage($path);

		// Remove read permission
		chmod($path, 0000);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to open file');

		try {
			$file->open('r');
		}
		finally {
			chmod($path, 0644);
		}
	}

	public function testOpenNonWritableFileForWriting(): void {
		$path = $this->createTempFile('readonly.txt', 'content');
		$file = File::manage($path);

		// Remove write permission
		chmod($path, 0444);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to open file');

		try {
			$file->open('w');
		}
		finally {
			chmod($path, 0644);
		}
	}

	public function testGetSizeOnInaccessibleFile(): void {

		$path = $this->createTempFile('secret.txt', 'secret');
		$file = File::manage($path);

		// delete file
		@unlink($path);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to get file size');

		$file->getSize();
	}

	public function testGetMimeTypeOnInaccessibleFile(): void {
		$path = $this->createTempFile('secret.txt', 'secret');
		$file = File::manage($path);

		// Remove all permissions
		chmod($path, 0000);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to detect MIME type');

		try {
			$file->getMimeType();
		}
		finally {
			chmod($path, 0644);
		}
	}

	public function testTouchOnNonWritableFile(): void {

		$dir = $this->tempDir . '/readonly';
		mkdir($dir, 0755);

		$source = $dir . '/file.txt';
		file_put_contents($source, 'content');
		$this->tempFiles[] = $source;

		$file = File::manage($source);

		// Make directory read-only (can't delete/move files)
		chmod($dir, 0000);

		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Failed to touch file');

		try {
			$file->touch();
		}
		finally {
			chmod($dir, 0755);
		}
	}
}