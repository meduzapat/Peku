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
use Peku\Abstractions\Collection;

/**
 * Unit tests for Collection class
 *
 * Tests read-only collection with string values.
 */
class CollectionTest extends TestCase {

	// ========================================================================
	// Constructor Tests
	// ========================================================================

	public function testConstructorWithEmptyArray(): void {
		$collection = new Collection();
		$this->assertSame([], $collection->all());
		$this->assertTrue($collection->isEmpty());
	}

	public function testConstructorWithItems(): void {
		$items      = ['name' => 'John', 'age' => '25'];
		$collection = new Collection($items);

		$this->assertSame($items, $collection->all());
		$this->assertFalse($collection->isEmpty());
	}

	// ========================================================================
	// get() Tests - String Values
	// ========================================================================

	public function testGetReturnsStringValue(): void {
		$collection = new Collection(['name' => 'John']);
		$this->assertSame('John', $collection->get('name'));
	}

	public function testGetReturnsEmptyStringForMissingKey(): void {
		$collection = new Collection();
		$this->assertSame('', $collection->get('missing'));
	}

	public function testGetReturnsDefaultForMissingKey(): void {
		$collection = new Collection();
		$this->assertSame('default', $collection->get('missing', 'default'));
		$this->assertSame(123, $collection->get('missing', 123));
	}

	public function testGetWithNullDefaultReturnEmptyString(): void {
		$collection = new Collection();
		$this->assertSame('', $collection->get('missing', null));
	}

	// ========================================================================
	// get() Tests - Non-String Values (Fallback Behavior)
	// ========================================================================

	public function testGetReturnsEmptyStringForNonStringValue(): void {
		$collection = new Collection(['count' => 123, 'active' => true]);

		$this->assertSame('', $collection->get('count'));
		$this->assertSame('', $collection->get('active'));
	}

	public function testGetReturnsDefaultForNonStringValue(): void {
		$collection = new Collection(['count' => 123]);

		$this->assertSame('default', $collection->get('count', 'default'));
		$this->assertSame(999, $collection->get('count', 999));
	}

	public function testGetWithNullValueReturnsDefault(): void {
		$collection = new Collection(['nullable' => null]);

		// null is not a string, so falls back to default
		$this->assertSame('', $collection->get('nullable'));
		$this->assertSame('default', $collection->get('nullable', 'default'));
	}

	public function testGetWithArrayValueReturnsDefault(): void {
		$collection = new Collection(['data' => ['nested' => 'value']]);

		$this->assertSame('', $collection->get('data'));
		$this->assertSame('fallback', $collection->get('data', 'fallback'));
	}

	// ========================================================================
	// has() Tests
	// ========================================================================

	public function testHasReturnsTrueForExistingKey(): void {
		$collection = new Collection(['name' => 'John']);
		$this->assertTrue($collection->has('name'));
	}

	public function testHasReturnsFalseForMissingKey(): void {
		$collection = new Collection(['name' => 'John']);
		$this->assertFalse($collection->has('missing'));
	}

	public function testHasReturnsTrueForNullValue(): void {
		// array_key_exists checks for key presence, not value
		$collection = new Collection(['nullable' => null]);
		$this->assertTrue($collection->has('nullable'));
	}

	// ========================================================================
	// all() Tests
	// ========================================================================

	public function testAllReturnsAllItems(): void {
		$items      = ['name' => 'John', 'age' => '25', 'city' => 'NYC'];
		$collection = new Collection($items);

		$this->assertSame($items, $collection->all());
	}

	public function testAllReturnsEmptyArray(): void {
		$collection = new Collection();
		$this->assertSame([], $collection->all());
	}

	// ========================================================================
	// keys() Tests
	// ========================================================================

	public function testKeysReturnsAllKeys(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25']);
		$this->assertSame(['name', 'age'], $collection->keys());
	}

	public function testKeysReturnsEmptyArray(): void {
		$collection = new Collection();
		$this->assertSame([], $collection->keys());
	}

	// ========================================================================
	// values() Tests
	// ========================================================================

	public function testValuesReturnsAllValuesReindexed(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25']);
		$this->assertSame(['John', '25'], $collection->values());
	}

	public function testValuesReturnsEmptyArray(): void {
		$collection = new Collection();
		$this->assertSame([], $collection->values());
	}

	public function testValuesReindexesNumericKeys(): void {
		$collection = new Collection([5 => 'five', 10 => 'ten', 2 => 'two']);
		$this->assertSame(['five', 'ten', 'two'], $collection->values());
	}

	// ========================================================================
	// isEmpty() Tests
	// ========================================================================

	public function testIsEmptyReturnsTrueForEmptyCollection(): void {
		$collection = new Collection();
		$this->assertTrue($collection->isEmpty());
	}

	public function testIsEmptyReturnsFalseForNonEmptyCollection(): void {
		$collection = new Collection(['name' => 'John']);
		$this->assertFalse($collection->isEmpty());
	}

	// ========================================================================
	// first() Tests
	// ========================================================================

	public function testFirstReturnsFirstValue(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25', 'city' => 'NYC']);
		$this->assertSame('John', $collection->first());
	}

	public function testFirstReturnsNullForEmptyCollection(): void {
		$collection = new Collection();
		$this->assertNull($collection->first());
	}

	public function testFirstWithSingleItem(): void {
		$collection = new Collection(['only' => 'one']);
		$this->assertSame('one', $collection->first());
	}

	public function testFirstPreservesInsertionOrder(): void {
		$collection = new Collection(['z' => 'last', 'a' => 'first', 'm' => 'middle']);
		$this->assertSame('last', $collection->first());
	}

	// ========================================================================
	// last() Tests
	// ========================================================================

	public function testLastReturnsLastValue(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25', 'city' => 'NYC']);
		$this->assertSame('NYC', $collection->last());
	}

	public function testLastReturnsNullForEmptyCollection(): void {
		$collection = new Collection();
		$this->assertNull($collection->last());
	}

	public function testLastWithSingleItem(): void {
		$collection = new Collection(['only' => 'one']);
		$this->assertSame('one', $collection->last());
	}

	public function testLastPreservesInsertionOrder(): void {
		$collection = new Collection(['z' => 'first', 'a' => 'middle', 'm' => 'last']);
		$this->assertSame('last', $collection->last());
	}

	// ========================================================================
	// only() Tests
	// ========================================================================

	public function testOnlyReturnsRequestedKeys(): void {
		$collection = new Collection([
			'name'  => 'John',
			'age'   => '25',
			'email' => 'john@example.com',
			'city'  => 'NYC'
		]);

		$result = $collection->only(['name', 'email']);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('email', $result);
		$this->assertArrayNotHasKey('age', $result);
		$this->assertArrayNotHasKey('city', $result);
	}

	public function testOnlyOmitsMissingKeys(): void {
		$collection = new Collection(['name' => 'John']);
		$result     = $collection->only(['name', 'missing']);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayNotHasKey('missing', $result);
	}

	public function testOnlyWithDefaults(): void {
		$collection = new Collection(['name' => 'John']);
		$result     = $collection->only(['name', 'age'], ['age' => '0']);

		$this->assertSame(['age' => '0', 'name' => 'John'], $result);
	}

	public function testOnlyDoesNotOverrideExistingWithDefaults(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25']);
		$result     = $collection->only(['name', 'age'], ['age' => '0', 'name' => 'Default']);

		// Existing values should NOT be overridden by defaults
		$this->assertSame('John', $result['name']);
		$this->assertSame('25', $result['age']);
	}

	public function testOnlyWithEmptyKeys(): void {
		$collection = new Collection(['name' => 'John']);
		$result     = $collection->only([]);

		$this->assertSame([], $result);
	}

	// ========================================================================
	// except() Tests
	// ========================================================================

	public function testExceptExcludesSpecifiedKeys(): void {
		$collection = new Collection([
			'name'     => 'John',
			'age'      => '25',
			'password' => 'secret',
			'token'    => 'xyz123'
		]);

		$result = $collection->except(['password', 'token']);

		$this->assertCount(2, $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('age', $result);
		$this->assertArrayNotHasKey('password', $result);
		$this->assertArrayNotHasKey('token', $result);
	}

	public function testExceptWithNonExistentKeys(): void {
		$collection = new Collection(['name' => 'John']);
		$result     = $collection->except(['missing']);

		$this->assertSame(['name' => 'John'], $result);
	}

	public function testExceptWithEmptyKeys(): void {
		$items      = ['name' => 'John', 'age' => '25'];
		$collection = new Collection($items);
		$result     = $collection->except([]);

		$this->assertSame($items, $result);
	}

	// ========================================================================
	// hasAny() Tests
	// ========================================================================

	public function testHasAnyReturnsTrueWhenOneKeyExists(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25']);

		$this->assertTrue($collection->hasAny(['name', 'missing']));
		$this->assertTrue($collection->hasAny(['missing', 'age']));
	}

	public function testHasAnyReturnsFalseWhenNoKeysExist(): void {
		$collection = new Collection(['name' => 'John']);

		$this->assertFalse($collection->hasAny(['missing', 'notfound']));
	}

	public function testHasAnyWithEmptyKeys(): void {
		$collection = new Collection(['name' => 'John']);
		$this->assertFalse($collection->hasAny([]));
	}

	// ========================================================================
	// hasAll() Tests
	// ========================================================================

	public function testHasAllReturnsTrueWhenAllKeysExist(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25', 'city' => 'NYC']);

		$this->assertTrue($collection->hasAll(['name', 'age']));
		$this->assertTrue($collection->hasAll(['name', 'age', 'city']));
	}

	public function testHasAllReturnsFalseWhenOneKeyMissing(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25']);

		$this->assertFalse($collection->hasAll(['name', 'missing']));
		$this->assertFalse($collection->hasAll(['name', 'age', 'city']));
	}

	public function testHasAllWithEmptyKeys(): void {
		$collection = new Collection(['name' => 'John']);
		// Empty array should always return true (no keys to check)
		$this->assertTrue($collection->hasAll([]));
	}

	// ========================================================================
	// count() Tests (Countable Interface)
	// ========================================================================

	public function testCountReturnsNumberOfItems(): void {
		$collection = new Collection(['name' => 'John', 'age' => '25', 'city' => 'NYC']);
		$this->assertSame(3, $collection->count());
	}

	public function testCountReturnsZeroForEmpty(): void {
		$collection = new Collection();
		$this->assertSame(0, $collection->count());
	}

	public function testCountWorksWithCountFunction(): void {
		$collection = new Collection(['a' => '1', 'b' => '2']);
		$this->assertSame(2, count($collection));
	}

	// ========================================================================
	// getIterator() Tests (IteratorAggregate Interface)
	// ========================================================================

	public function testGetIteratorAllowsForeach(): void {
		$items      = ['name' => 'John', 'age' => '25'];
		$collection = new Collection($items);

		$result = [];
		foreach ($collection as $key => $value) {
			$result[$key] = $value;
		}

		$this->assertSame($items, $result);
	}

	// ========================================================================
	// Integration Tests
	// ========================================================================

	public function testRealWorldHttpHeadersScenario(): void {
		$headers = new Collection([
			'Content-Type'   => 'application/json',
			'Accept'         => 'application/json',
			'Authorization'  => 'Bearer token123',
			'X-Request-Id'   => 'abc-123',
			'Content-Length' => '1024' // Note: string value
		]);

		// Get specific header
		$this->assertSame('application/json', $headers->get('Content-Type'));

		// Get with default
		$this->assertSame('en', $headers->get('Accept-Language', 'en'));

		// Check headers exist
		$this->assertTrue($headers->has('Authorization'));
		$this->assertFalse($headers->has('X-Custom'));

		// Get security-sensitive headers only
		$secure = $headers->except(['Authorization', 'X-Request-Id']);
		$this->assertArrayNotHasKey('Authorization', $secure);
		$this->assertArrayHasKey('Content-Type', $secure);

		// Check required headers
		$this->assertTrue($headers->hasAll(['Content-Type', 'Accept']));

		// Count headers
		$this->assertSame(5, $headers->count());
	}

	public function testRealWorldServerVariablesScenario(): void {
		$server = new Collection([
			'REQUEST_METHOD'  => 'POST',
			'REQUEST_URI'     => '/api/users',
			'SERVER_PROTOCOL' => 'HTTP/1.1',
			'REMOTE_ADDR'     => '192.168.1.1',
			'HTTP_HOST'       => 'example.com'
		]);

		$this->assertSame('POST', $server->get('REQUEST_METHOD'));
		$this->assertSame('80', $server->get('SERVER_PORT', '80'));
		$this->assertFalse($server->hasAny(['HTTPS', 'HTTP_X_FORWARDED_PROTO']));

		$httpVars = array_filter(
			$server->all(),
			function($key) {
				return str_starts_with($key, 'HTTP_');
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->assertCount(1, $httpVars);
		$this->assertArrayHasKey('HTTP_HOST', $httpVars);
	}
}
