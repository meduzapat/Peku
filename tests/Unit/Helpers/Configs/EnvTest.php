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

use Peku\Helpers\Configs\Env;
use Peku\Helpers\Configs\ConfigException;
use Peku\Tests\Fixtures\TestCase;

/**
 * Unit tests for Env config implementation
 *
 * Tests environment variable reading with prefix support and required/optional parameters.
 * Type casting is tested in ValuesTest. Base Config functionality is tested in ConfigTest.
 */
class EnvTest extends TestCase {

	private array $envVarsToClean = [];

	protected function tearDown(): void {
		foreach ($this->envVarsToClean as $varName) {
			\putenv($varName);
		}
		parent::tearDown();
	}

	private function setEnv(string $name, string $value): void {
		\putenv("$name=$value");
		$this->envVarsToClean[] = $name;
	}

	// ========================================================================
	// Basic Functionality
	// ========================================================================

	public function testReadsEnvironmentVariablesWithoutPrefix(): void {
		$this->setEnv('DATABASE_HOST', 'localhost');
		$this->setEnv('DATABASE_PORT', '3306');

		$config = new Env([
			'database' => [
				'host' => 'default',
				'port' => 0,
			],
		]);

		$this->assertSame('localhost', $config->get('database', 'host'));
		$this->assertSame(3306,        $config->get('database', 'port'));
	}

	public function testReadsEnvironmentVariablesWithPrefix(): void {
		$this->setEnv('MYAPP_DATABASE_HOST', 'localhost');

		$config = new Env([
			'database' => ['host' => 'default'],
		], 'myapp');

		$this->assertSame('localhost', $config->get('database', 'host'));
	}

	public function testDelegatesTypeCastingToValuesClass(): void {
		$this->setEnv('APP_DEBUG', 'true');
		$this->setEnv('APP_TIMEOUT', '30.5');

		$config = new Env([
			'app' => [
				'debug'   => false,
				'timeout' => 0.0,
			],
		]);

		$this->assertTrue($config->get('app', 'debug'));
		$this->assertSame(30.5, $config->get('app', 'timeout'));
	}

	// ========================================================================
	// Default Value Handling
	// ========================================================================

	public function testUsesDefaultWhenEnvNotSet(): void {
		$config = new Env([
			'database' => [
				'host' => 'localhost',
				'port' => 3306,
			],
		]);

		$this->assertSame('localhost', $config->get('database', 'host'));
		$this->assertSame(3306,        $config->get('database', 'port'));
	}

	public function testOverridesDefaultWhenEnvSet(): void {
		$this->setEnv('DATABASE_HOST', 'remotehost');

		$config = new Env([
			'database' => ['host' => 'localhost'],
		]);

		$this->assertSame('remotehost', $config->get('database', 'host'));
	}

	public function testMixedDefaultsAndEnvValues(): void {
		$this->setEnv('DATABASE_HOST', 'remotehost');

		$config = new Env([
			'database' => [
				'host' => 'localhost',
				'port' => 3306,
			],
		]);

		$this->assertSame('remotehost', $config->get('database', 'host'));
		$this->assertSame(3306,         $config->get('database', 'port'));
	}

	// ========================================================================
	// Required Environment Variables (No Default)
	// ========================================================================

	public function testRequiredEnvVariableExists(): void {
		$this->setEnv('DATABASE_HOST', 'localhost');

		$config = new Env([
			'database' => ['host'],
		]);

		$this->assertSame('localhost', $config->get('database', 'host'));
	}

	public function testRequiredEnvVariableReturnsStringWithoutCasting(): void {
		$this->setEnv('DATABASE_PORT', '3306');

		$config = new Env([
			'database' => ['port'],
		]);

		$this->assertSame('3306', $config->get('database', 'port'));
		$this->assertIsString($config->get('database', 'port'));
	}

	public function testThrowsExceptionWhenRequiredEnvMissing(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('Missing required environment variable: DATABASE_HOST');

		new Env([
			'database' => ['host'],
		]);
	}

	public function testThrowsExceptionWithPrefixWhenRequiredEnvMissing(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('Missing required environment variable: MYAPP_DATABASE_HOST');

		new Env([
			'database' => ['host'],
		], 'MYAPP');
	}

	public function testMixedRequiredAndOptionalVariables(): void {
		$this->setEnv('DATABASE_HOST', 'localhost');

		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('Missing required environment variable: DATABASE_NAME');

		new Env([
			'database' => [
				'host',
				'port' => 3306,
				'name',
			],
		]);
	}

	// ========================================================================
	// Multiple Sections
	// ========================================================================

	public function testHandlesMultipleSections(): void {
		$this->setEnv('DATABASE_HOST', 'localhost');
		$this->setEnv('APP_NAME', 'TestApp');

		$config = new Env([
			'database' => ['host' => 'default'],
			'app'      => ['name' => 'default'],
		]);

		$this->assertSame('localhost', $config->get('database', 'host'));
		$this->assertSame('TestApp',   $config->get('app',      'name'));
	}

	public function testSectionsAreIndependent(): void {
		$this->setEnv('DATABASE_NAME', 'db1');
		$this->setEnv('APP_NAME', 'app1');

		$config = new Env([
			'database' => ['name' => 'default'],
			'app'      => ['name' => 'default'],
		]);

		$this->assertSame('db1',  $config->get('database', 'name'));
		$this->assertSame('app1', $config->get('app',      'name'));
	}

	// ========================================================================
	// Invalid Configuration
	// ========================================================================

	public function testThrowsExceptionWhenSectionIsNotArray(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage("Section 'database' must be an array");

		new Env([
			'database' => 'not an array',
		]);
	}

	public function testThrowsExceptionWhenSectionIsScalar(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage("Section 'app' must be an array");

		new Env([
			'app' => 123,
		]);
	}

	// ========================================================================
	// Edge Cases
	// ========================================================================

	public function testHandlesEmptySection(): void {
		$config = new Env([
			'empty' => [],
		]);

		$this->assertSame([], $config->getSection('empty'));
	}

	public function testEmptyStringTriggersDefault(): void {
		$this->setEnv('APP_NAME', '');

		$config = new Env([
			'app' => ['name' => 'default'],
		]);

		$this->assertSame('default', $config->get('app', 'name'));
	}

	// ========================================================================
	// Integration with Base Config Methods
	// ========================================================================

	public function testGetSectionReturnsCompleteData(): void {
		$this->setEnv('DATABASE_HOST', 'localhost');
		$this->setEnv('DATABASE_PORT', '3306');

		$config = new Env([
			'database' => [
				'host' => 'default',
				'port' => 0,
			],
		]);

		$section = $config->getSection('database');
		$this->assertSame([
			'host' => 'localhost',
			'port' => 3306,
		], $section);
	}

	public function testGetAllReturnsCompleteStructure(): void {
		$this->setEnv('DATABASE_HOST', 'localhost');
		$this->setEnv('APP_NAME', 'TestApp');

		$config = new Env([
			'database' => ['host' => 'default'],
			'app'      => ['name' => 'default'],
		]);

		$all = $config->getAll();
		$this->assertSame([
			'database' => ['host' => 'localhost'],
			'app'      => ['name' => 'TestApp'],
		], $all);
	}
}