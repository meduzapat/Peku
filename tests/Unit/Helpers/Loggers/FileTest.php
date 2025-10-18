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

namespace Peku\Tests\Unit\Helpers\Loggers;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Loggers\File;
use Peku\Helpers\Loggers\LogLevel;
use RuntimeException;

/**
 * Unit tests for File logger implementation
 */
class FileTest extends TestCase {

	private string $tempFile;

	protected function setUp(): void {
		$this->tempFile = sys_get_temp_dir() . '/peku_test_' . uniqid() . '.log';
	}

	protected function tearDown(): void {
		if (file_exists($this->tempFile)) {
			@unlink($this->tempFile);
		}
	}

	// ========================================================================
	// Basic Logging Tests
	// ========================================================================

	public function testExistingWritableFile(): void {
		touch($this->tempFile);
		chmod($this->tempFile, 0644);

		$logger = new File($this->tempFile);
		$logger->logError('Test message');

		$this->assertFileExists($this->tempFile);
		$contents = file_get_contents($this->tempFile);
		$this->assertStringContainsString('[error] Test message', $contents);
	}

	public function testWritesLogToFile(): void {
		$logger = new File($this->tempFile);
		$logger->logWarning('Test message');

		$this->assertFileExists($this->tempFile);
		$contents = file_get_contents($this->tempFile);
		$this->assertStringContainsString('[warning] Test message', $contents);
	}

	public function testAppendsMultipleLogs(): void {
		$logger = new File($this->tempFile);
		$logger->logInfo('First');
		$logger->logWarning('Second');
		$logger->logError('Third');

		$contents = file_get_contents($this->tempFile);
		$this->assertStringContainsString('[info] First',     $contents);
		$this->assertStringContainsString('[warning] Second', $contents);
		$this->assertStringContainsString('[error] Third',    $contents);
	}

	public function testCreatesDirectoryWhenCreateDirIsTrue(): void {
		$dir     = sys_get_temp_dir() . '/peku_test_' . uniqid();
		$logFile = $dir . '/test.log';

		$logger = new File($logFile, true);
		$logger->logInfo('Test');

		$this->assertFileExists($logFile);
		$this->assertDirectoryExists($dir);

		// Cleanup
		@unlink($logFile);
		@rmdir($dir);
	}

	// ========================================================================
	// Log Level Tests
	// ========================================================================

	/**
	 * @dataProvider logLevelProvider
	 */
	public function testLogsWithCorrectLevel(LogLevel $level, string $expectedLabel): void {
		$logger = new File($this->tempFile);
		$logger->log('Test', $level);

		$contents = file_get_contents($this->tempFile);
		$this->assertStringContainsString("[$expectedLabel]", $contents);
	}

	public static function logLevelProvider(): array {
		return [
			'debug'    => [LogLevel::Debug,    'debug'],
			'info'     => [LogLevel::Info,     'info'],
			'warning'  => [LogLevel::Warning,  'warning'],
			'error'    => [LogLevel::Error,    'error'],
			'critical' => [LogLevel::Critical, 'critical'],
		];
	}

	// ========================================================================
	// Timestamp Tests
	// ========================================================================

	public function testIncludesTimestamp(): void {
		$logger = new File($this->tempFile);
		$logger->logDebug('Test');

		$contents = file_get_contents($this->tempFile);
		// Format: [2025-01-20 14:30:45]
		$this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $contents);
	}

	// ========================================================================
	// Error Handling Tests
	// ========================================================================

	public function testThrowsExceptionForNonExistentDirectory(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Log directory does not exist');

		new File('/nonexistent/directory/test.log');
	}

	public function testThrowsExceptionForNonWritableFile(): void {
		// Create a non-writable file
		touch($this->tempFile);
		chmod($this->tempFile, 0444); // Read-only

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('not writable');

		new File($this->tempFile);

		// Cleanup
		chmod($this->tempFile, 0644);
	}

	public function testThrowsExceptionForNonWritableDirectory(): void {
		$dir     = sys_get_temp_dir() . '/peku_test_' . uniqid();
		$logFile = $dir . '/test.log';

		mkdir($dir, 0555); // Read-only directory

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Log directory is not writable');

		try {
			new File($logFile);
		}
		finally {
			// Cleanup
			chmod($dir, 0755);
			@rmdir($dir);
		}
	}

	public function testThrowsExceptionWhenMkdirFails(): void {

		// This test abuses that the program does not check if the file exist for dir
		$blockingFile = sys_get_temp_dir() . '/peku_test_' . uniqid();
		// Create file that blocks mkdir
		touch($blockingFile);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Failed to create log directory');

		try {
			new File($blockingFile . '/test.log', true);
		}
		finally {
			@unlink($blockingFile);
		}
	}
}