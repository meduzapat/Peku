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

namespace Peku\Tests\Unit\Messages;

use PHPUnit\Framework\TestCase;
use Peku\Messages\{Request, RequestType};
use Peku\Abstractions\Collection;
use Peku\Helpers\Http\Extractors\Extractable;
use Peku\Tests\Fixtures\Mocks\MockExtractor;

/**
 * Unit tests for Request abstract class
 *
 * Tests only Request-specific behavior:
 * - Constructor/extract lifecycle
 * - Type detection
 * - values() accessor returns proper Collection
 * - wants() format detection
 * - Instance independence
 */
class RequestTest extends TestCase {

	private TestRequest $request;

	protected function setUp(): void {
		$this->request = new TestRequest();
	}

	/*******************************
	 * Constructor & Extract Tests *
	 *******************************/

	/**
	 * Verify extract() is called exactly once during construction
	 */
	public function testConstructorCallsExtractOnce(): void {
		$request = new TestRequest();
		$this->assertSame(1, $request->extractCalled);
	}

	public function testExtractPopulatesType(): void {
		$this->assertSame(RequestType::Get, $this->request->getType());
	}

	public function testExtractPopulatesCollection(): void {
		// Verify values() returns Collection
		$this->assertInstanceOf(Collection::class, $this->request->values());
		$this->assertSame(TestRequest::TEST_DATA, $this->request->values()->all());
	}

	/*****************
	 * wants() Tests *
	 *****************/

	public function testWantsReturnsFormat(): void {
		$this->assertSame('html', $this->request->wants());
	}

	public function testWantsCanReturnDifferentFormats(): void {
		$this->request->wantsFormat = 'json';
		$this->assertSame('json', $this->request->wants());
	}

	/***************************
	 * values() Accessor Tests *
	 ***************************/

	/**
	 * Verify values() returns Collection with proper data
	 */
	public function testValuesReturnsCollection(): void {
		$values = $this->request->values();

		$this->assertInstanceOf(Collection::class, $values);

		// Smoke test - full Collection testing is in CollectionTest
		$this->assertSame('John', $values->get('name'));
		$this->assertSame(25, $values->get('age', 0));
		$this->assertTrue($values->has('name'));
		$this->assertFalse($values->has('nonexistent'));
	}

	/*******************************
	 * Instance Independence Tests *
	 *******************************/

	/**
	 * Verify multiple Request instances operate independently
	 */
	public function testMultipleRequestsAreIndependent(): void {
		$request1 = new TestRequest();
		$request2 = new EmptyRequest();

		$this->assertNotEmpty($request1->values()->all());
		$this->assertEmpty($request2->values()->all());
		$this->assertSame(RequestType::Get, $request1->getType());
		$this->assertSame(RequestType::Cli, $request2->getType());
	}
}

class EmptyRequest extends Request {
	protected function extract(): void {
		$this->type   = RequestType::Cli;
		$this->values = new Collection([]);
	}

	public function wants(): string {
		return 'txt';
	}

	protected function createExtractor(): Extractable {
		return new MockExtractor();
	}
}

/**
 * Concrete test request implementations
 */
class TestRequest extends Request {

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

	public int    $extractCalled = 0;
	public string $wantsFormat   = 'html';

	protected function extract(): void {
		$this->extractCalled++;
		$this->type   = RequestType::Get;
		$this->values = new Collection(self::TEST_DATA);
	}

	public function wants(): string {
		return $this->wantsFormat;
	}

	protected function createExtractor(): Extractable {
		return new MockExtractor();
	}
}
