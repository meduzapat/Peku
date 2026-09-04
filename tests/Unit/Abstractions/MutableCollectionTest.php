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
use Peku\Abstractions\MutableCollection;

/**
 * Unit tests for MutableCollection class
 *
 * Tests only write operations. Read operations inherited from Collection
 * are tested in CollectionTest.php
 */
class MutableCollectionTest extends TestCase {

	/***************
	 * set() Tests *
	 ***************/

	public function testSetAddsNewKey(): void {
		$collection = new MutableCollection();
		$collection->set('name', 'John');

		$this->assertSame(['name' => 'John'], $collection->all());
	}

	public function testSetOverwritesExistingKey(): void {
		$collection = new MutableCollection(['name' => 'John']);
		$collection->set('name', 'Jane');

		$this->assertSame(['name' => 'Jane'], $collection->all());
	}

	public function testSetAcceptsMixedTypes(): void {
		$collection = new MutableCollection();
		$collection->set('string', 'value');
		$collection->set('int', 42);
		$collection->set('float', 3.14);
		$collection->set('bool', true);
		$collection->set('array', [1, 2, 3]);
		$collection->set('null', null);

		$items = $collection->all();
		$this->assertSame('value', $items['string']);
		$this->assertSame(42, $items['int']);
		$this->assertSame(3.14, $items['float']);
		$this->assertTrue($items['bool']);
		$this->assertSame([1, 2, 3], $items['array']);
		$this->assertNull($items['null']);
	}

	public function testSetReturnsStaticForChaining(): void {
		$collection = new MutableCollection();
		$result = $collection->set('key', 'value');

		$this->assertSame($collection, $result);
	}

	/******************
	 * remove() Tests *
	 ******************/

	public function testRemoveDeletesExistingKey(): void {
		$collection = new MutableCollection(['name' => 'John', 'age' => 30]);
		$collection->remove('name');

		$this->assertSame(['age' => 30], $collection->all());
	}

	public function testRemoveNonExistentKeyIsNoOp(): void {
		$collection = new MutableCollection(['name' => 'John']);
		$collection->remove('nonexistent');

		$this->assertSame(['name' => 'John'], $collection->all());
	}

	public function testRemoveFromEmptyCollectionIsNoOp(): void {
		$collection = new MutableCollection();
		$collection->remove('key');

		$this->assertSame([], $collection->all());
	}

	public function testRemoveReturnsStaticForChaining(): void {
		$collection = new MutableCollection(['key' => 'value']);
		$result = $collection->remove('key');

		$this->assertSame($collection, $result);
	}

	/*****************
	 * clear() Tests *
	 *****************/

	public function testClearRemovesAllItems(): void {
		$collection = new MutableCollection(['name' => 'John', 'age' => 30, 'email' => 'john@example.com']);
		$collection->clear();

		$this->assertSame([], $collection->all());
	}

	public function testClearOnEmptyCollectionIsNoOp(): void {
		$collection = new MutableCollection();
		$collection->clear();

		$this->assertSame([], $collection->all());
	}

	public function testClearReturnsStaticForChaining(): void {
		$collection = new MutableCollection(['key' => 'value']);
		$result = $collection->clear();

		$this->assertSame($collection, $result);
	}

	/*****************
	 * merge() Tests *
	 *****************/

	public function testMergeAddsNewKeys(): void {
		$collection = new MutableCollection(['name' => 'John']);
		$collection->merge(['age' => 30, 'email' => 'john@example.com']);

		$this->assertSame([
			'name' => 'John',
			'age' => 30,
			'email' => 'john@example.com'
		], $collection->all());
	}

	public function testMergeOverwritesExistingKeys(): void {
		$collection = new MutableCollection(['name' => 'John', 'age' => 30]);
		$collection->merge(['age' => 35, 'city' => 'NYC']);

		$this->assertSame([
			'name' => 'John',
			'age' => 35,
			'city' => 'NYC'
		], $collection->all());
	}

	public function testMergeEmptyArrayIsNoOp(): void {
		$collection = new MutableCollection(['name' => 'John']);
		$collection->merge([]);

		$this->assertSame(['name' => 'John'], $collection->all());
	}

	public function testMergeIntoEmptyCollection(): void {
		$collection = new MutableCollection();
		$collection->merge(['name' => 'John', 'age' => 30]);

		$this->assertSame(['name' => 'John', 'age' => 30], $collection->all());
	}

	public function testMergeReturnsStaticForChaining(): void {
		$collection = new MutableCollection();
		$result = $collection->merge(['key' => 'value']);

		$this->assertSame($collection, $result);
	}

	public function testMergePreservesNumericKeysWhenNoOverlap(): void {
		$collection = new MutableCollection([0 => 'zero', 1 => 'one']);
		$collection->merge([2 => 'two']);

		$this->assertSame([
			0 => 'zero',
			1 => 'one',
			2 => 'two'
		], $collection->all());
	}

	public function testMergeReindexesNumericKeys(): void {
		$collection = new MutableCollection([0 => 'zero', 1 => 'one']);
		$collection->merge([0 => 'ZERO', 2 => 'two']);

		// Spread operator reindexes numeric keys, doesn't overwrite
		$this->assertSame([
			0 => 'zero',
			1 => 'one',
			2 => 'ZERO',
			3 => 'two'
		], $collection->all());
	}

	/****************
	 * pull() Tests *
	 ****************/

	public function testPullReturnsAndRemovesValue(): void {
		$collection = new MutableCollection(['name' => 'John', 'age' => 30]);
		$value = $collection->pull('name');

		$this->assertSame('John', $value);
		$this->assertSame(['age' => 30], $collection->all());
	}

	public function testPullWithDefaultForMissingKey(): void {
		$collection = new MutableCollection(['name' => 'John']);
		$value = $collection->pull('missing', 'default');

		$this->assertSame('default', $value);
		$this->assertSame(['name' => 'John'], $collection->all());
	}

	public function testPullReturnsNullForMissingKeyWithoutDefault(): void {
		$collection = new MutableCollection(['name' => 'John']);
		$value = $collection->pull('missing');

		$this->assertNull($value);
		$this->assertSame(['name' => 'John'], $collection->all());
	}

	public function testPullCastsStringValueWithDefault(): void {
		$collection = new MutableCollection(['age' => '25', 'score' => '98.5']);

		$age = $collection->pull('age', 0);
		$score = $collection->pull('score', 0.0);

		$this->assertSame(25, $age);
		$this->assertSame(98.5, $score);
		$this->assertSame([], $collection->all());
	}

	public function testPullNonStringValueAsIs(): void {
		$collection = new MutableCollection(['count' => 123, 'tags' => ['php']]);

		$count = $collection->pull('count', 0);
		$tags = $collection->pull('tags');

		$this->assertSame(123, $count);
		$this->assertSame(['php'], $tags);
		$this->assertSame([], $collection->all());
	}

	public function testPullFromEmptyCollection(): void {
		$collection = new MutableCollection();
		$value = $collection->pull('key', 'default');

		$this->assertSame('default', $value);
		$this->assertSame([], $collection->all());
	}

	/*************************
	 * Method Chaining Tests *
	 *************************/

	public function testAllMethodsSupportChaining(): void {
		$collection = new MutableCollection();

		$result = $collection
		->set('name', 'John')
		->set('age', 30)
		->set('temp', 'remove me')
		->remove('temp')
		->merge(['city' => 'NYC', 'country' => 'USA']);

		$this->assertSame($collection, $result);
		$this->assertSame([
			'name' => 'John',
			'age' => 30,
			'city' => 'NYC',
			'country' => 'USA'
		], $collection->all());
	}

	public function testChainingWithClear(): void {
		$collection = new MutableCollection(['old' => 'data']);

		$result = $collection
		->set('new', 'value')
		->clear()
		->set('fresh', 'start')
		->merge(['more' => 'data']);

		$this->assertSame($collection, $result);
		$this->assertSame([
			'fresh' => 'start',
			'more' => 'data'
		], $collection->all());
	}

	/**************
	 * Edge Cases *
	 **************/

	public function testMultipleSetsSameKey(): void {
		$collection = new MutableCollection();
		$collection->set('key', 'first')
		->set('key', 'second')
		->set('key', 'third');

		$this->assertSame(['key' => 'third'], $collection->all());
	}

	public function testMultipleRemovesSameKey(): void {
		$collection = new MutableCollection(['key' => 'value']);
		$collection->remove('key')
		->remove('key')
		->remove('key');

		$this->assertSame([], $collection->all());
	}

	public function testMultipleClearCalls(): void {
		$collection = new MutableCollection(['key' => 'value']);
		$collection->clear()
		->clear()
		->clear();

		$this->assertSame([], $collection->all());
	}

	public function testMultipleMergeCalls(): void {
		$collection = new MutableCollection(['a' => 1]);
		$collection->merge(['b' => 2])
		->merge(['c' => 3])
		->merge(['a' => 10, 'd' => 4]);

		$this->assertSame([
			'a' => 10,
			'b' => 2,
			'c' => 3,
			'd' => 4
		], $collection->all());
	}

	public function testMultiplePullsSameKey(): void {
		$collection = new MutableCollection(['key' => 'value']);

		$first = $collection->pull('key');
		$second = $collection->pull('key', 'default');

		$this->assertSame('value', $first);
		$this->assertSame('default', $second);
		$this->assertSame([], $collection->all());
	}
}
