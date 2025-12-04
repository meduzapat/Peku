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
use Peku\Messages\Http\HttpResponse;

/**
 * Unit tests for HttpResponse class
 *
 * Streamlined version focusing on HTTP-specific features.
 * Base Response functionality (setContent, setCode, getContent, getCode)
 * is tested in ResponseTest.php
 */
class HttpResponseTest extends TestCase {

	// ========================================================================
	// Constructor Tests (Consolidated)
	// ========================================================================

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
			$this->assertSame($headers, $response->getHeaders());
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

	// ========================================================================
	// Header Management Tests
	// ========================================================================

	public function testHeaderManagement(): void {
		$response = new HttpResponse();

		// Set header
		$response->setHeader('X-Custom', 'value');
		$this->assertSame('value', $response->getHeader('X-Custom'));
		$this->assertTrue($response->hasHeader('X-Custom'));

		// Get non-existent
		$this->assertNull($response->getHeader('X-Missing'));
		$this->assertFalse($response->hasHeader('X-Missing'));

		// Remove header
		$response->removeHeader('X-Custom');
		$this->assertFalse($response->hasHeader('X-Custom'));
	}

	public function testSetHeadersPreservesExisting(): void {
		$response = new HttpResponse();
		$response->setHeader('X-First', 'value1');

		$response->setHeaders([
			'X-Second' => 'value2',
			'X-First'  => 'overwritten',
		]);

		$this->assertSame('overwritten', $response->getHeader('X-First'));
		$this->assertSame('value2', $response->getHeader('X-Second'));
	}

	public function testHeaderNameCaseSensitivity(): void {
		$response = new HttpResponse();
		$response->setHeader('Content-Type', 'text/html');

		// Headers are case-sensitive in storage
		$this->assertSame('text/html', $response->getHeader('Content-Type'));
		$this->assertNull($response->getHeader('content-type'));
	}

	// ========================================================================
	// Convenience Header Methods
	// ========================================================================

	/**
	 * @dataProvider contentTypeProvider
	 */
	public function testSetContentType(string $contentType, string $charset, string $expected): void {
		$response = new HttpResponse();
		$response->setContentType($contentType, $charset);

		$this->assertSame($expected, $response->getHeader('Content-Type'));
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

		$this->assertSame('max-age=3600', $response->getHeader('Cache-Control'));
	}

	public function testNoCache(): void {
		$response = new HttpResponse();
		$response->noCache();

		$this->assertSame('no-cache, no-store, must-revalidate', $response->getHeader('Cache-Control'));
		$this->assertSame('no-cache', $response->getHeader('Pragma'));
		$this->assertSame('0', $response->getHeader('Expires'));
	}

	// ========================================================================
	// Status Code & Messages Tests (Consolidated)
	// ========================================================================

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

	// ========================================================================
	// Factory Methods Tests (Consolidated)
	// ========================================================================

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

	// ========================================================================
	// Fluent Interface Tests (Consolidated)
	// ========================================================================

	public function testFluentInterfaceChaining(): void {
		$response = (new HttpResponse())
		->setContent('test')
		->setCode(201)
		->setHeader('X-Custom', 'value')
		->setContentType('application/json')
		->setCacheControl('no-cache');

		$this->assertSame('test', $response->getContent());
		$this->assertSame(201, $response->getCode());
		$this->assertSame('value', $response->getHeader('X-Custom'));
		$this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));
		$this->assertSame('no-cache', $response->getHeader('Cache-Control'));
	}

	public function testFactoryMethodChaining(): void {
		$response = HttpResponse::ok('Success')
		->setHeader('X-Version', '1.0')
		->setContentType('text/plain');

		$this->assertSame('Success', $response->getContent());
		$this->assertSame(200, $response->getCode());
		$this->assertSame('1.0', $response->getHeader('X-Version'));
	}

	// ========================================================================
	// Send Tests
	// ========================================================================

	public function testSendOutputsContent(): void {
		$response = new HttpResponse('Hello World');

		ob_start();
		$response->send();
		$output = ob_get_clean();

		$this->assertSame('Hello World', $output);
	}

	public function testSendHandlesNonStringContent(): void {
		$response = new HttpResponse(['key' => 'value']);

		ob_start();
		$response->send();
		$output = ob_get_clean();

		// Non-string content is cast to string
		$this->assertIsString($output);
	}

	public function testSendCanBeCalledMultipleTimes(): void {
		$response = new HttpResponse('test');

		ob_start();
		$response->send();
		$response->send();
		$output = ob_get_clean();

		$this->assertSame('testtest', $output);
		$this->assertTrue($response->headersSent());
	}

	// ========================================================================
	// Edge Cases
	// ========================================================================

	public function testEmptyContentHandling(): void {
		$response = new HttpResponse('');
		$this->assertSame('', $response->getContent());

		ob_start();
		$response->send();
		$output = ob_get_clean();
		$this->assertSame('', $output);
	}

	public function testComplexContentTypes(): void {
		$response = new HttpResponse();

		$response->setContentType('application/vnd.api+json', 'utf-8');
		$this->assertSame('application/vnd.api+json; charset=utf-8', $response->getHeader('Content-Type'));

		$response->setContentType('text/html', '');
		$this->assertSame('text/html', $response->getHeader('Content-Type'));
	}

	public function testMultipleHeaderUpdates(): void {
		$response = new HttpResponse();

		$response->setHeaders(['X-First' => 'a', 'X-Second' => 'b']);
		$response->setHeaders(['X-First' => 'updated', 'X-Third' => 'c']);

		$headers = $response->getHeaders();
		$this->assertSame('updated', $headers['X-First']);
		$this->assertSame('b', $headers['X-Second']);
		$this->assertSame('c', $headers['X-Third']);
	}
}
