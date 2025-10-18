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

namespace Peku\Tests\Unit\Helpers\Configs;

use Peku\Helpers\Configs\Php;
use Peku\Helpers\Configs\ConfigException;
use Peku\Helpers\Files\FileException;
use Peku\Tests\Fixtures\TestCase;

/**
 * Unit tests for Php config implementation
 *
 * Tests only the import() behavior specific to Php class.
 * Base Config functionality is tested in ConfigTest.php
 */
class PhpTest extends TestCase {

	private string $validConfigFile;
	private string $invalidConfigFile;
	private string $nonArrayConfigFile;

	protected function setUp(): void {
		parent::setUp();

		// Create temporary config files for testing
		$this->validConfigFile = \sys_get_temp_dir() . '/peku_test_valid.php';
		\file_put_contents($this->validConfigFile, <<<'PHP'
<?php
return [
	'database' => [
		'host' => 'localhost',
		'port' => 3306,
	],
	'app' => [
		'name' => 'TestApp',
	],
];
PHP
		);

		$this->invalidConfigFile = \sys_get_temp_dir() . '/peku_test_invalid.php';
		\file_put_contents($this->invalidConfigFile, '<?php syntax error here');

		$this->nonArrayConfigFile = \sys_get_temp_dir() . '/peku_test_non_array.php';
		\file_put_contents($this->nonArrayConfigFile, '<?php return "not an array";');
	}

	protected function tearDown(): void {
		// Clean up temporary files
		@\unlink($this->validConfigFile);
		@\unlink($this->invalidConfigFile);
		@\unlink($this->nonArrayConfigFile);

		parent::tearDown();
	}

	// ========================================================================
	// Success Cases
	// ========================================================================

	public function testLoadsValidPhpConfigFile(): void {
		$config = new Php(['file' => $this->validConfigFile]);

		$this->assertSame('localhost', $config->get('database', 'host'));
		$this->assertSame(3306, $config->get('database', 'port'));
		$this->assertSame('TestApp', $config->get('app', 'name'));
	}

	public function testReturnsCompleteArrayStructure(): void {
		$config = new Php(['file' => $this->validConfigFile]);
		$all    = $config->getAll();

		$this->assertIsArray($all);
		$this->assertArrayHasKey('database', $all);
		$this->assertArrayHasKey('app', $all);
	}

	// ========================================================================
	// Exception Cases - Missing/Invalid Parameters
	// ========================================================================

	public function testThrowsExceptionWhenFileParameterMissing(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('file parameter required');

		new Php([]);
	}

	public function testThrowsExceptionWhenFileParameterIsEmpty(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('file parameter required');

		new Php(['file' => null]);
	}

	// ========================================================================
	// Exception Cases - File Not Found
	// ========================================================================

	public function testThrowsExceptionWhenFileNotFound(): void {
		$this->expectException(FileException::class);
		$this->expectExceptionMessage('Config file not found:');

		new Php(['file' => '/nonexistent/path/config.php']);
	}

	public function testExceptionIncludesFilenameWhenNotFound(): void {
		$filename = '/tmp/missing_file.php';

		try {
			new Php(['file' => $filename]);
			$this->fail('Expected ConfigException was not thrown');
		}
		catch (FileException $e) {
			$this->assertStringContainsString($filename, $e->getMessage());
		}
	}

	// ========================================================================
	// Exception Cases - Invalid Return Type
	// ========================================================================

	public function testThrowsExceptionWhenFileDoesNotReturnArray(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('Config file must return an array:');

		new Php(['file' => $this->nonArrayConfigFile]);
	}

	public function testExceptionIncludesFilenameWhenNotArray(): void {
		try {
			new Php(['file' => $this->nonArrayConfigFile]);
			$this->fail('Expected ConfigException was not thrown');
		}
		catch (ConfigException $e) {
			$this->assertStringContainsString($this->nonArrayConfigFile, $e->getMessage());
		}
	}

	// ========================================================================
	// Edge Cases
	// ========================================================================

	public function testHandlesEmptyArrayReturn(): void {
		$emptyFile = \sys_get_temp_dir() . '/peku_test_empty.php';
		\file_put_contents($emptyFile, '<?php return [];');

		$config = new Php(['file' => $emptyFile]);

		$this->assertSame([], $config->getAll());
		$this->assertFalse($config->hasSection('anything'));

		@\unlink($emptyFile);
	}
}