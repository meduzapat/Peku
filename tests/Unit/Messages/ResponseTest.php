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
use Peku\Messages\Response;

/**
 * Concrete test response for testing Response abstract class
 */
class TestResponse extends Response {

	public bool   $sendCalled   = false;
	public string $sentContent  = '';

	public function getCodeMessage(): string {
		return "Code: {$this->code}";
	}

	protected function processContent(): string {
		$this->sendCalled  = true;
		$this->sentContent = (string)$this->content;
		return $this->content;
	}

	protected function validate(mixed $content): void {}

}

/**
 * Unit tests for Response abstract base class
 */
class ResponseTest extends TestCase {

	private TestResponse $response;

	protected function setUp(): void {
		$this->response = new TestResponse();
	}

	// ========================================================================
	// Constructor Tests
	// ========================================================================

	public function testConstructorWithDefaults(): void {
		$response = new TestResponse();

		$this->assertSame('', $response->getContent());
		$this->assertSame(0, $response->getCode());
	}

	public function testConstructorWithContentAndCode(): void {
		$response = new TestResponse('Not Found', 404);

		$this->assertSame('Not Found', $response->getContent());
		$this->assertSame(404, $response->getCode());
	}

	public function testSetContentReplacesExisting(): void {
		$response = new TestResponse('Not Found', 404);
		$response->setContent('First');
		$this->assertSame('First', $response->getContent());

		$response->setContent('Second');
		$this->assertSame('Second', $response->getContent());
	}

	// ========================================================================
	// getCodeMessage() Tests
	// ========================================================================

	public function testGetCodeMessageReturnsImplementationMessage(): void {
		$response = new TestResponse('content', 404);

		// TestResponse returns simple "Code: X" format
		$this->assertSame('Code: 404', $response->getCodeMessage());
	}

	public function testGetCodeMessageWithDifferentCodes(): void {
		$response1 = new TestResponse('', 200);
		$response2 = new TestResponse('', 500);

		$this->assertSame('Code: 200', $response1->getCodeMessage());
		$this->assertSame('Code: 500', $response2->getCodeMessage());
	}

	// ========================================================================
	// Method Chaining Tests (Fluent Interface)
	// ========================================================================

	public function testFluentInterfaceSetContent(): void {
		$result = $this->response->setContent('test');

		$this->assertSame($this->response, $result);
	}

	public function testFluentInterfaceSetCode(): void {
		$result = $this->response->setCode(404);

		$this->assertSame($this->response, $result);
	}

	public function testFluentInterfaceChaining(): void {
		$result = $this->response
		->setCode(201)
		->setContent('Created');

		$this->assertSame($this->response, $result);
		$this->assertSame(201, $this->response->getCode());
		$this->assertSame('Created', $this->response->getContent());
	}

	public function testComplexChaining(): void {
		$response = (new TestResponse())
		->setCode(200)
		->setContent('First')
		->setCode(404)
		->setContent('Not Found');

		$this->assertSame(404, $response->getCode());
		$this->assertSame('Not Found', $response->getContent());
	}

	public function testFluentInterfaceWithConstructor(): void {
		$response = (new TestResponse('Initial', 200))
		->setContent('Modified')
		->setCode(201);

		$this->assertSame('Modified', $response->getContent());
		$this->assertSame(201, $response->getCode());
	}

	// ========================================================================
	// send() Abstract Method Tests
	// ========================================================================

	public function testSendCallsImplementation(): void {
		$this->response->setContent('Test output');

		$this->assertFalse($this->response->sendCalled);

		$this->response->send();

		$this->assertTrue($this->response->sendCalled);
		$this->assertSame('Test output', $this->response->sentContent);
	}

	public function testSendWithEmptyContent(): void {
		$this->response->send();

		$this->assertTrue($this->response->sendCalled);
		$this->assertSame('', $this->response->sentContent);
	}

	// ========================================================================
	// Integration Tests
	// ========================================================================

	public function testCompleteWorkflow(): void {
		$response = new TestResponse('Initial', 200);

		$this->assertSame('Initial', $response->getContent());
		$this->assertSame(200, $response->getCode());

		$response->setCode(404)->setContent('Not Found');

		$this->assertSame('Not Found', $response->getContent());
		$this->assertSame(404, $response->getCode());

		$response->send();

		$this->assertTrue($response->sendCalled);
		$this->assertSame('Not Found', $response->sentContent);
	}

	public function testMultipleResponseInstancesAreIndependent(): void {
		$response1 = new TestResponse('Content 1', 200);
		$response2 = new TestResponse('Content 2', 404);

		$this->assertSame('Content 1', $response1->getContent());
		$this->assertSame(200, $response1->getCode());

		$this->assertSame('Content 2', $response2->getContent());
		$this->assertSame(404, $response2->getCode());

		$response1->setContent('Modified 1');

		$this->assertSame('Modified 1', $response1->getContent());
		$this->assertSame('Content 2', $response2->getContent());
	}

	// ========================================================================
	// Edge Cases
	// ========================================================================

	public function testZeroCode(): void {
		$response = new TestResponse('', 0);

		$this->assertSame(0, $response->getCode());
	}

	public function testNegativeCode(): void {
		$response = new TestResponse('', -1);

		$this->assertSame(-1, $response->getCode());
	}

	public function testLargeCode(): void {
		$response = new TestResponse('', 999);

		$this->assertSame(999, $response->getCode());
	}

	public function testEmptyStringContent(): void {
		$this->response->setContent('');

		$this->assertSame('', $this->response->getContent());
	}

	public function testWhitespaceContent(): void {
		$this->response->setContent('   ');

		$this->assertSame('   ', $this->response->getContent());
	}

	public function testComplexArrayContent(): void {
		$data = [
			'user' => [
				'id'   => 123,
				'name' => 'John',
				'tags' => ['php', 'testing'],
			],
			'meta' => [
				'timestamp' => time(),
			],
		];

		$this->response->setContent($data);

		$this->assertSame($data, $this->response->getContent());
	}
}
