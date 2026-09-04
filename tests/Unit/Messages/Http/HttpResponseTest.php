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

namespace Peku\Tests\Unit\Messages\Http;

use PHPUnit\Framework\TestCase;
use phpmock\phpunit\PHPMock;
use Peku\Messages\Http\{HttpResponse, HttpRequest};

/**
 * Unit tests for HttpResponse class
 *
 * Streamlined version focusing on HTTP-specific features.
 * Base Response functionality (setContent, setCode, getContent, getCode)
 * is tested in ResponseTest.php
 */
class HttpResponseTest extends TestCase {

	use PHPMock;

	/************************************
	 * Constructor Tests (Consolidated) *
	 ************************************/

	/**
	 * @dataProvider constructorProvider
	 */
	public function testConstructor(
		mixed $content,
		int   $code,
		array $headers,
		mixed $expectedContent,
		int   $expectedCode
	): void {
		$response = new HttpResponse($content, $code, $headers);

		$this->assertSame($expectedContent, $response->getContent());
		$this->assertSame($expectedCode, $response->getCode());
		$this->assertSame($headers, $response->headers()->all());
	}

	public static function constructorProvider(): array {
		return [
			'defaults' => [
				'content'         => '',
				'code'            => 200,
				'headers'         => [],
				'expectedContent' => '',
				'expectedCode'    => 200,
			],
			'with content' => [
				'content'         => 'Hello World',
				'code'            => 200,
				'headers'         => [],
				'expectedContent' => 'Hello World',
				'expectedCode'    => 200,
			],
			'with status' => [
				'content'         => 'Not Found',
				'code'            => 404,
				'headers'         => [],
				'expectedContent' => 'Not Found',
				'expectedCode'    => 404,
			],
			'with headers' => [
				'content'         => 'test',
				'code'            => 200,
				'headers'         => ['Content-Type' => 'application/json'],
				'expectedContent' => 'test',
				'expectedCode'    => 200,
			],
		];
	}

	/*****************************
	 * Protocol Management Tests *
	 *****************************/

	public function testDefaultProtocol(): void {
		$response = new HttpResponse();
		$this->assertSame('HTTP/1.1', $response->getProtocol());
	}

	public function testSetProtocol(): void {
		$response = new HttpResponse();
		$response->setProtocol('HTTP/2');
		$this->assertSame('HTTP/2', $response->getProtocol());
	}

	public function testInquiryExtractsProtocolFromRequest(): void {
		$request = $this->createMock(HttpRequest::class);
		$request->method('getProtocolVersion')->willReturn('2');

		$response = new HttpResponse();
		$response->inquiry($request);

		$this->assertSame('HTTP/2', $response->getProtocol());
	}

	/***********************************************
	 * Header Management Tests - Collection Access *
	 ***********************************************/

	public function testHeadersReturnsCollection(): void {
		$response = new HttpResponse();
		$this->assertInstanceOf(\Peku\Abstractions\MutableCollection::class, $response->headers());
	}

	public function testHeadersCollectionOperations(): void {
		$response = new HttpResponse();
		$headers  = $response->headers();

		// Set header
		$headers->set('X-Custom', 'value');
		$this->assertSame('value', $headers->get('X-Custom'));
		$this->assertTrue($headers->has('X-Custom'));

		// Get non-existent
		$this->assertNull($headers->get('X-Missing'));
		$this->assertSame('default', $headers->get('X-Missing', 'default'));
		$this->assertFalse($headers->has('X-Missing'));

		// Remove header
		$headers->remove('X-Custom');
		$this->assertFalse($headers->has('X-Custom'));
	}

	public function testHeadersMergePreservesExisting(): void {
		$response = new HttpResponse();
		$headers  = $response->headers();

		$headers->set('X-First', 'value1');
		$headers->merge([
			'X-Second' => 'value2',
			'X-First'  => 'overwritten',
		]);

		$this->assertSame('overwritten', $headers->get('X-First'));
		$this->assertSame('value2', $headers->get('X-Second'));
	}

	public function testHeaderNameCaseSensitivity(): void {
		$response = new HttpResponse();
		$headers  = $response->headers();

		$headers->set('Content-Type', 'text/html');

		// Headers are case-sensitive in storage
		$this->assertSame('text/html', $headers->get('Content-Type'));
		$this->assertNull($headers->get('content-type'));
	}

	/******************************
	 * Convenience Header Methods *
	 ******************************/

	/**
	 * @dataProvider contentTypeProvider
	 */
	public function testSetContentType(string $contentType, string $charset, string $expected): void {
		$response = new HttpResponse();
		$response->setContentType($contentType, $charset);

		$this->assertSame($expected, $response->headers()->get('Content-Type'));
	}

	public static function contentTypeProvider(): array {
		return [
			'json with default charset' => [
				'contentType' => 'application/json',
				'charset'     => 'utf-8',
				'expected'    => 'application/json; charset=utf-8',
			],
			'json with custom charset' => [
				'contentType' => 'application/json',
				'charset'     => 'iso-8859-1',
				'expected'    => 'application/json; charset=iso-8859-1',
			],
			'json without charset' => [
				'contentType' => 'application/json',
				'charset'     => '',
				'expected'    => 'application/json',
			],
		];
	}

	public function testSetCacheControl(): void {
		$response = new HttpResponse();
		$response->setCacheControl('max-age=3600');

		$this->assertSame('max-age=3600', $response->headers()->get('Cache-Control'));
	}

	public function testNoCache(): void {
		$response = new HttpResponse();
		$response->noCache();

		$this->assertSame('no-cache, no-store, must-revalidate', $response->headers()->get('Cache-Control'));
		$this->assertSame('no-cache', $response->headers()->get('Pragma'));
		$this->assertSame('0', $response->headers()->get('Expires'));
	}

	/***********************************************
	 * Status Code & Messages Tests (Consolidated) *
	 ***********************************************/

	/**
	 * @dataProvider statusMessageProvider
	 */
	public function testGetStatusMessage(int $code, string $expectedMessage): void {
		$response = new HttpResponse('', $code);
		$this->assertSame($expectedMessage, $response->getCodeMessage());
	}

	public static function statusMessageProvider(): array {
		return [
			// 2xx Success
			'200' => [200, 'OK'],
			'201' => [201, 'Created'],
			'202' => [202, 'Accepted'],
			'204' => [204, 'No Content'],

			// 3xx Redirection
			'301' => [301, 'Moved Permanently'],
			'302' => [302, 'Found'],
			'304' => [304, 'Not Modified'],

			// 4xx Client Errors
			'400' => [400, 'Bad Request'],
			'401' => [401, 'Unauthorized'],
			'403' => [403, 'Forbidden'],
			'404' => [404, 'Not Found'],
			'429' => [429, 'Too Many Requests'],

			// 5xx Server Errors
			'500' => [500, 'Internal Server Error'],
			'503' => [503, 'Service Unavailable'],

			// Unknown
			'999' => [999, 'Unknown Status Code'],
		];
	}

	/****************************************
	 * Factory Methods Tests (Consolidated) *
	 ****************************************/

	/**
	 * @dataProvider factoryMethodProvider
	 */
	public function testFactoryMethods(
		string $method,
		mixed  $content,
		int    $expectedCode,
		string $expectedMessage
	): void {
		$response = HttpResponse::$method($content);

		$this->assertInstanceOf(HttpResponse::class, $response);
		$this->assertSame($content, $response->getContent());
		$this->assertSame($expectedCode, $response->getCode());
		$this->assertSame($expectedMessage, $response->getCodeMessage());
	}

	public static function factoryMethodProvider(): array {
		return [
			'ok'           => ['ok',           'Success',   200, 'OK'],
			'created'      => ['created',      'Created',   201, 'Created'],
			'badRequest'   => ['badRequest',   'Bad data',  400, 'Bad Request'],
			'unauthorized' => ['unauthorized', 'Login',     401, 'Unauthorized'],
			'forbidden'    => ['forbidden',    'No access', 403, 'Forbidden'],
			'notFound'     => ['notFound',     'Missing',   404, 'Not Found'],
			'serverError'  => ['serverError',  'Crashed',   500, 'Internal Server Error'],
		];
	}

	public function testNoContentFactory(): void {
		$response = HttpResponse::noContent();

		$this->assertSame('', $response->getContent());
		$this->assertSame(204, $response->getCode());
	}

	/*****************************************
	 * Fluent Interface Tests (Consolidated) *
	 *****************************************/

	public function testFluentInterfaceChaining(): void {
		$response = (new HttpResponse())
			->setContent('test')
			->setCode(201)
			->setContentType('application/json')
			->setCacheControl('no-cache')
			->setProtocol('HTTP/2');

		// Custom headers set via collection
		$response->headers()->set('X-Custom', 'value');

		$this->assertSame('test', $response->getContent());
		$this->assertSame(201, $response->getCode());
		$this->assertSame('value', $response->headers()->get('X-Custom'));
		$this->assertStringContainsString('application/json', $response->headers()->get('Content-Type'));
		$this->assertSame('no-cache', $response->headers()->get('Cache-Control'));
		$this->assertSame('HTTP/2', $response->getProtocol());
	}

	public function testFactoryMethodChaining(): void {
		$response = HttpResponse::ok('Success')
			->setContentType('text/plain')
			->setProtocol('HTTP/2');

		// Custom headers set via collection
		$response->headers()->set('X-Version', '1.0');

		$this->assertSame('Success', $response->getContent());
		$this->assertSame(200, $response->getCode());
		$this->assertSame('1.0', $response->headers()->get('X-Version'));
		$this->assertSame('HTTP/2', $response->getProtocol());
	}

	/**************
	 * Send Tests *
	 **************/

	public function testSendOutputsContent(): void {
		$response = new HttpResponse('Hello World');
		$this->expectOutputString('Hello World');
		$response->send();
	}

	public function testSendCanBeCalledMultipleTimes(): void {
		$response = new HttpResponse('test');
		$this->expectOutputString('testtest');
		$response->send();
		$response->send();
	}

	/**************
	 * Edge Cases *
	 **************/

	public function testEmptyContentHandling(): void {
		$response = new HttpResponse('');
		$this->assertSame('', $response->getContent());
		$this->expectOutputString('');
		$response->send();
	}

	public function testComplexContentTypes(): void {
		$response = new HttpResponse();

		$response->setContentType('application/vnd.api+json', 'utf-8');
		$this->assertSame('application/vnd.api+json; charset=utf-8', $response->headers()->get('Content-Type'));

		$response->setContentType('text/html', '');
		$this->assertSame('text/html', $response->headers()->get('Content-Type'));
	}

	public function testMultipleHeaderUpdates(): void {
		$response = new HttpResponse();
		$headers  = $response->headers();

		$headers->merge(['X-First' => 'a', 'X-Second' => 'b']);
		$headers->merge(['X-First' => 'updated', 'X-Third' => 'c']);

		$this->assertSame('updated', $headers->get('X-First'));
		$this->assertSame('b', $headers->get('X-Second'));
		$this->assertSame('c', $headers->get('X-Third'));
	}

	/********************
	 * Validation Tests *
	 ********************/

	public function testValidateThrowsForNonStringContent(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('HTTP response content must be string or Stringable');

		new HttpResponse(['array' => 'content']);
	}

	public function testValidateThrowsWithDebugType(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Received: array');

		new HttpResponse(['data']);
	}

	public function testValidateAcceptsStringableObject(): void {
		$stringable = new class implements \Stringable {
			public function __toString(): string {
				return 'stringable';
			}
		};

		$response = new HttpResponse($stringable);
		$this->assertSame($stringable, $response->getContent());
	}

	/*************************************
	 * Header Sending Tests (with Mocks) *
	 *************************************/

	/**
	 * @runInSeparateProcess
	 */
	public function testSendHeadersSkipsWhenAlreadySent(): void {
		$headersSent = $this->getFunctionMock('Peku\\Messages\\Http', 'headers_sent');
		$headersSent->expects($this->once())->willReturn(true);

		$header = $this->getFunctionMock('Peku\\Messages\\Http', 'header');
		$header->expects($this->never()); // Should not be called

		$response = new HttpResponse('test', 200);

		ob_start();
		$response->send(); // Triggers sendHeaders()
		ob_end_clean();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSendHeadersCallsHeaderFunction(): void {
		$headersSent = $this->getFunctionMock('Peku\\Messages\\Http', 'headers_sent');
		$headersSent->expects($this->once())->willReturn(false);

		$header = $this->getFunctionMock('Peku\\Messages\\Http', 'header');
		// Status line + 2 custom headers = 3 calls
		$header->expects($this->exactly(3));

		$response = new HttpResponse('test', 200);
		$response->headers()->set('X-Custom', 'value1');
		$response->headers()->set('X-Test', 'value2');

		ob_start();
		$response->send();
		ob_end_clean();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSendHeadersUsesCustomProtocol(): void {
		$headersSent = $this->getFunctionMock('Peku\\Messages\\Http', 'headers_sent');
		$headersSent->expects($this->once())->willReturn(false);

		$header = $this->getFunctionMock('Peku\\Messages\\Http', 'header');
		$header
			->expects($this->once())
			->with('HTTP/2.0 404 Not Found', true, 404);

		$response = new HttpResponse('', 404);
		$response->setProtocol('HTTP/2.0'); // Set protocol explicitly

		ob_start();
		$response->send();
		ob_end_clean();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSendHeadersDefaultsToHttp11(): void {
		$headersSent = $this->getFunctionMock('Peku\\Messages\\Http', 'headers_sent');
		$headersSent->expects($this->once())->willReturn(false);

		$header = $this->getFunctionMock('Peku\\Messages\\Http', 'header');
		$header
			->expects($this->once())
			->with('HTTP/1.1 500 Internal Server Error', true, 500);

		$response = new HttpResponse('error', 500);
		// No setProtocol() call - should default to HTTP/1.1

		ob_start();
		$response->send();
		ob_end_clean();
	}
}
