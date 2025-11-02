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
use Peku\Messages\Request;
use Peku\Messages\RequestType;

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
		$this->values = self::TEST_DATA;
	}

	public function wants(): string {
		return $this->wantsFormat;
	}

	// Expose protected property for testing
	public function getValues(): array {
		return $this->values;
	}
}

class EmptyRequest extends Request {
	protected function extract(): void {
		$this->type   = RequestType::Cli;
		$this->values = [];
	}

	public function wants(): string {
		return 'txt';
	}
}

/**
 * Unit tests for Request abstract class
 */
class RequestTest extends TestCase {

	private TestRequest $request;

	protected function setUp(): void {
		$this->request = new TestRequest();
	}

	// ========================================================================
	// Constructor & Extract Tests
	// ========================================================================

	/**
	 * Verify extract() is called exactly once during construction
	 * This ensures child classes properly initialize their data
	 */
	public function testConstructorCallsExtractOnce(): void {
		$request = new TestRequest();
		$this->assertSame(1, $request->extractCalled);
	}

	public function testExtractPopulatesType(): void {
		$this->assertSame(RequestType::Get, $this->request->getType());
	}

	public function testExtractPopulatesValues(): void {
		$this->assertSame(TestRequest::TEST_DATA, $this->request->getValues());
	}

	// ========================================================================
	// wants() Tests
	// ========================================================================

	public function testWantsReturnsFormat(): void {
		$this->assertSame('html', $this->request->wants());
	}

	public function testWantsCanReturnDifferentFormats(): void {
		$this->request->wantsFormat = 'json';
		$this->assertSame('json', $this->request->wants());
	}

	// ========================================================================
	// get() Tests
	// ========================================================================

	/**
	 * Verify get() retrieves values and returns defaults when not found
	 */
	public function testGetRetrievesValuesOrDefaults(): void {
		// Existing values
		$this->assertSame('John', $this->request->get('name'));
		$this->assertSame('john@example.com', $this->request->get('email'));
		// Array
		$this->assertSame(TestRequest::TEST_DATA['tags'], $this->request->get('tags'));

		// Missing values with defaults
		$this->assertNull($this->request->get('nonexistent'));
		$this->assertSame('default', $this->request->get('missing', 'default'));
	}

	// ========================================================================
	// has() Tests
	// ========================================================================

	/**
	 * Verify has() correctly checks key existence
	 */
	public function testHasChecksKeyExistence(): void {
		$this->assertTrue($this->request->has('name'));
		$this->assertTrue($this->request->has('email'));
		$this->assertFalse($this->request->has('nonexistent'));
	}

	// ========================================================================
	// all() Tests
	// ========================================================================

	/**
	 * Verify all() returns complete data set
	 */
	public function testAllReturnsAllValues(): void {
		$all = $this->request->all();

		$this->assertIsArray($all);
		$this->assertCount(8, $all);
		$this->assertSame(TestRequest::TEST_DATA, $all);
	}

	// ========================================================================
	// Integration Tests
	// ========================================================================

	/**
	 * Verify helper methods from RetrievableHelpers trait work correctly
	 * (Detailed trait testing is in RetrievableHelpersTest)
	 */
	public function testRetrievableHelpersTraitWorks(): void {
		// Quick smoke test - trait provides only/except/hasAny/hasAll
		$this->assertSame(['name' => 'John'], $this->request->only(['name']));
		$this->assertCount(7, $this->request->except(['name']));
		$this->assertTrue($this->request->hasAny(['name', 'missing']));
		$this->assertTrue($this->request->hasAll(['name', 'email']));
	}

	/**
	 * Verify multiple Request instances operate independently
	 */
	public function testMultipleRequestsAreIndependent(): void {
		$request1 = new TestRequest();
		$request2 = new EmptyRequest();

		$this->assertNotEmpty($request1->all());
		$this->assertEmpty($request2->all());
		$this->assertSame(RequestType::Get, $request1->getType());
		$this->assertSame(RequestType::Cli, $request2->getType());
	}
}
