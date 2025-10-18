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

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Configs\Config;

/**
 * Unit tests for Config abstract class
 */
class ConfigTest extends TestCase {

	public const TEST_DATA = [
		'database' => [
			'host'     => 'localhost',
			'port'     => 3306,
			'username' => 'root',
			'password' => 'secret',
			'enabled'  => true,
		],
		'app' => [
			'name'     => 'PekuTest',
			'debug'    => false,
			'timezone' => 'UTC',
		],
		'empty_section' => [],
	];

	private Config $config;

	protected function setUp(): void {
		// Create concrete implementation for testing
		$this->config = new class(['test' => 'data']) extends Config {
			protected function import(array $sourceInfo): array {
				return ConfigTest::TEST_DATA;
			}
		};
	}

	// ========================================================================
	// Constructor & Import Tests
	// ========================================================================

	public function testConstructorCallsImport(): void {
		$sourceInfo = ['filename' => 'test.ini'];
		$called     = false;
		$passedInfo = null;

		new class($sourceInfo, $called, $passedInfo) extends Config {
			public function __construct(
				array $sourceInfo,
				private bool &$called,
				private ?array &$passedInfo
			) {
				parent::__construct($sourceInfo);
			}

			protected function import(array $sourceInfo): array {
				$this->called     = true;
				$this->passedInfo = $sourceInfo;
				return [];
			}
		};

		$this->assertTrue($called, 'import() should be called during construction');
		$this->assertSame(['filename' => 'test.ini'], $passedInfo);
	}

	// ========================================================================
	// get() Method Tests
	// ========================================================================

	public function testGetReturnsExistingValue(): void {
		$this->assertSame('localhost', $this->config->get('database', 'host'));
		$this->assertSame(3306,        $this->config->get('database', 'port'));
		$this->assertSame('PekuTest',  $this->config->get('app',      'name'));
		$this->assertTrue($this->config->get('database', 'enabled'));
	}

	public function testGetReturnsDefaultWhenKeyNotFound(): void {
		$this->assertNull($this->config->get('database', 'nonexistent'));
		$this->assertNull($this->config->get('missing',  'key', null));
		$this->assertSame('default', $this->config->get('database', 'missing', 'default'));
		$this->assertSame(9999,      $this->config->get('app',      'missing', 9999));
	}

	public function testGetReturnsDefaultWhenSectionNotFound(): void {
		$this->assertNull($this->config->get('nonexistent', 'key'));
		$this->assertSame('fallback', $this->config->get('missing', 'key', 'fallback'));
	}

	public function testGetHandlesMixedTypes(): void {
		$this->assertIsString($this->config->get('database', 'host'));
		$this->assertIsInt($this->config->get('database',    'port'));
		$this->assertIsBool($this->config->get('database',   'enabled'));
	}

	// ========================================================================
	// getSection() Method Tests
	// ========================================================================

	public function testGetSectionReturnsCompleteSection(): void {
		$expected = self::TEST_DATA['database'];
		$section  = $this->config->getSection('database');

		$this->assertSame($expected, $section);
		$this->assertCount(5, $section);
	}

	public function testGetSectionReturnsDefaultWhenNotFound(): void {
		$this->assertSame([], $this->config->getSection('nonexistent'));
		$this->assertSame(['default'], $this->config->getSection('missing', ['default']));
	}

	public function testGetSectionReturnsEmptyArray(): void {
		$section = $this->config->getSection('empty_section');
		$this->assertSame([], $section);
	}

	// ========================================================================
	// hasSection() Method Tests
	// ========================================================================

	public function testHasSectionReturnsTrueWhenExists(): void {
		$this->assertTrue($this->config->hasSection('database'));
		$this->assertTrue($this->config->hasSection('app'));
		$this->assertTrue($this->config->hasSection('empty_section'));
	}

	public function testHasSectionReturnsFalseWhenNotExists(): void {
		$this->assertFalse($this->config->hasSection('nonexistent'));
		$this->assertFalse($this->config->hasSection('missing'));
	}

	// ========================================================================
	// has() Method Tests
	// ========================================================================

	public function testHasReturnsTrueWhenKeyExists(): void {
		$this->assertTrue($this->config->has('database', 'host'));
		$this->assertTrue($this->config->has('database', 'port'));
		$this->assertTrue($this->config->has('app',      'name'));
	}

	public function testHasReturnsFalseWhenKeyNotExists(): void {
		$this->assertFalse($this->config->has('database', 'nonexistent'));
		$this->assertFalse($this->config->has('app',      'missing'));
	}

	public function testHasReturnsFalseWhenSectionNotExists(): void {
		$this->assertFalse($this->config->has('nonexistent', 'key'));
	}

	// ========================================================================
	// getAll() Method Tests
	// ========================================================================

	public function testGetAllReturnsCompleteArray(): void {
		$all = $this->config->getAll();

		$this->assertSame(self::TEST_DATA, $all);
		$this->assertCount(3, $all);
	}

	// ========================================================================
	// IteratorAggregate Tests
	// ========================================================================

	public function testIterationProvidesCorrectData(): void {
		foreach ($this->config as $section => $data) {
			$this->assertSame(self::TEST_DATA[$section], $data);
		}
	}

	// ========================================================================
	// Edge Cases & Type Safety
	// ========================================================================

	public function testGetWithArrayDefault(): void {
		$default = ['custom' => 'array'];
		$result  = $this->config->get('missing', 'key', $default);
		$this->assertSame($default, $result);
	}

	public function testGetWithObjectDefault(): void {
		$default = new \stdClass();
		$result  = $this->config->get('missing', 'key', $default);
		$this->assertSame($default, $result);
	}

	// ========================================================================
	// Import Validation Tests
	// ========================================================================

	public function testImportMustReturnArray(): void {
		$this->expectException(\TypeError::class);

		new class(['test']) extends Config {
			protected function import(array $sourceInfo): array {
				// @phpstan-ignore-next-line - intentional type error for test
				return 'not an array';
			}
		};
	}

	public function testImportCanReturnEmptyArray(): void {
		$config = new class([]) extends Config {
			protected function import(array $sourceInfo): array {
				return [];
			}
		};

		$this->assertSame([], $config->getAll());
		$this->assertFalse($config->hasSection('anything'));
	}

	// ========================================================================
	// Integration Tests
	// ========================================================================

	public function testChainedAccess(): void {
		$this->assertTrue($this->config->hasSection('database'));
		$this->assertTrue($this->config->has('database', 'host'));
		$this->assertSame('localhost', $this->config->get('database', 'host'));

		$section = $this->config->getSection('database');
		$this->assertArrayHasKey('host', $section);
		$this->assertSame('localhost', $section['host']);
	}

	public function testMultipleInstancesAreIndependent(): void {
		$config1 = new class(['data1']) extends Config {
			protected function import(array $sourceInfo): array {
				return ['section' => ['key' => 'value1']];
			}
		};

		$config2 = new class(['data2']) extends Config {
			protected function import(array $sourceInfo): array {
				return ['section' => ['key' => 'value2']];
			}
		};

		$this->assertSame('value1', $config1->get('section', 'key'));
		$this->assertSame('value2', $config2->get('section', 'key'));
	}
}