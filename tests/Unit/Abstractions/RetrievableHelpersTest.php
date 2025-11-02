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

namespace Peku\Tests\Unit\Abstractions;

use PHPUnit\Framework\TestCase;
use Peku\Abstractions\Retrievable;
use Peku\Abstractions\RetrievableHelpers;

/**
 * Concrete test class implementing Retrievable with RetrievableHelpers trait
 */
class TestRetrievable implements Retrievable {

	use RetrievableHelpers;

	public const TEST_DATA = [
		'name'   => 'John',
		'age'    => '25',
		'email'  => 'john@example.com',
		'active' => 'true',
		'score'  => '98.5',
		'tags'   => ['php', 'testing'],
		'empty'  => '',
		'zero'   => '0',
	];

	private array $data;

	public function __construct(array $data = self::TEST_DATA) {
		$this->data = $data;
	}

	public function get(string $key, mixed $default = null): mixed {
		return $default;
	}

	public function has(string $key): bool {
		return true;
	}

	public function all(): array {
		return $this->data;
	}
}

class EmptyRetrievable implements Retrievable {

	use RetrievableHelpers;

	public function get(string $key, mixed $default = null): mixed {
		return $default;
	}

	public function has(string $key): bool {
		return false;
	}

	public function all(): array {
		return [];
	}
}

/**
 * Unit tests for RetrievableHelpers trait
 */
class RetrievableHelpersTest extends TestCase {

	private TestRetrievable $retrievable;

	protected function setUp(): void {
		$this->retrievable = new TestRetrievable();
	}

	// ========================================================================
	// only() Tests
	// ========================================================================

	/**
	 * Verify only() returns requested keys and omits others (including missing keys)
	 */
	public function testOnlyReturnsRequestedKeys(): void {
		$result = $this->retrievable->only(['name', 'email', 'nonexistent']);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('email', $result);
		$this->assertArrayNotHasKey('age', $result);
		$this->assertArrayNotHasKey('nonexistent', $result);
	}

	/**
	 * Verify defaults fill missing keys
	 */
	public function testOnlyWithDefaults(): void {
		$result = $this->retrievable->only(
			['name', 'country', 'city'],
			['country' => 'USA', 'city' => 'NYC']
			);

		$this->assertSame('John', $result['name']);
		$this->assertSame('USA', $result['country']);
		$this->assertSame('NYC', $result['city']);
	}

	/**
	 * Critical: Verify existing values are NOT overridden by defaults
	 */
	public function testOnlyWithDefaultsDoesNotOverrideExisting(): void {
		$result = $this->retrievable->only(
			['name', 'age'],
			['name' => 'Default', 'age' => '99']
			);

		// Existing values should NOT be overridden by defaults
		$this->assertSame('John', $result['name']);
		$this->assertSame('25', $result['age']);
	}

	public function testOnlyWithEmptyKeys(): void {
		$result = $this->retrievable->only([]);
		$this->assertSame([], $result);
	}

	// ========================================================================
	// except() Tests
	// ========================================================================

	/**
	 * Verify except() excludes specified keys and keeps others
	 */
	public function testExceptExcludesSpecifiedKeys(): void {
		$result = $this->retrievable->except(['age', 'score', 'nonexistent']);

		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('email', $result);
		$this->assertArrayNotHasKey('age', $result);
		$this->assertArrayNotHasKey('score', $result);
	}

	public function testExceptWithEmptyKeys(): void {
		$result = $this->retrievable->except([]);
		$this->assertSame($this->retrievable->all(), $result);
	}

	// ========================================================================
	// hasAny() Tests
	// ========================================================================

	/**
	 * Verify hasAny() returns true when at least one key exists
	 */
	public function testHasAnyReturnsTrueWhenOneKeyExists(): void {
		$this->assertTrue($this->retrievable->hasAny(['name', 'nonexistent']));
		$this->assertTrue($this->retrievable->hasAny(['missing', 'email']));
	}

	/**
	 * Verify hasAny() returns false when no keys exist
	 */
	public function testHasAnyReturnsFalseWhenNoKeysExist(): void {
		$this->assertFalse($this->retrievable->hasAny(['nonexistent', 'missing']));

		// Empty array and empty retrievable
		$this->assertFalse($this->retrievable->hasAny([]));
		$empty = new EmptyRetrievable();
		$this->assertFalse($empty->hasAny(['name', 'email']));
	}

	// ========================================================================
	// hasAll() Tests
	// ========================================================================

	/**
	 * Verify hasAll() returns true only when ALL keys exist
	 */
	public function testHasAllReturnsTrueWhenAllKeysExist(): void {
		$this->assertTrue($this->retrievable->hasAll(['name', 'email', 'age']));
	}

	/**
	 * Verify hasAll() returns false when ANY key is missing
	 */
	public function testHasAllReturnsFalseWhenOneKeyMissing(): void {
		$this->assertFalse($this->retrievable->hasAll(['name', 'nonexistent']));
		$this->assertFalse($this->retrievable->hasAll(['email', 'age', 'missing']));

		// Empty retrievable
		$empty = new EmptyRetrievable();
		$this->assertFalse($empty->hasAll(['name', 'email']));
	}

	/**
	 * Edge case: Empty array should always return true (no keys to check)
	 */
	public function testHasAllReturnsTrueWithEmptyArray(): void {
		$this->assertTrue($this->retrievable->hasAll([]));

		// Even on empty retrievable
		$empty = new EmptyRetrievable();
		$this->assertTrue($empty->hasAll([]));
	}

	// ========================================================================
	// Integration Tests
	// ========================================================================

	/**
	 * Verify trait works with custom data sets
	 */
	public function testCustomDataSet(): void {
		$custom = new TestRetrievable([
			'foo' => 'bar',
			'baz' => 'qux',
		]);

		$this->assertSame(['foo' => 'bar'], $custom->only(['foo']));
		$this->assertSame(['baz' => 'qux'], $custom->except(['foo']));
		$this->assertTrue($custom->hasAny(['foo', 'missing']));
		$this->assertTrue($custom->hasAll(['foo', 'baz']));
	}
}
