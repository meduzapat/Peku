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
use Peku\Messages\Http\HttpRequest;
use Peku\Messages\RequestType;
use Peku\Helpers\Http\Extractors\Extractor;
use Peku\Helpers\Http\UploadedFile;

/**
 * Unit tests for HttpRequest class
 *
 * Tests HTTP-specific features not covered by base RequestTest.
 * Does not re-test base Request functionality (get/has/all).
 */
class HttpRequestTest extends TestCase {

	private array $originalServer = [];

	protected function setUp(): void {
		$this->originalServer = $_SERVER;
	}

	protected function tearDown(): void {
		$_SERVER = $this->originalServer;
		HttpRequest::setDefaultExtractor(new \Peku\Helpers\Http\Extractors\Normal());
	}

	// ========================================================================
	// Test Helpers
	// ========================================================================

	/**
	 * Create mock extractor for controlling query/data/files
	 */
	private function createMockExtractor(
		array $query = [],
		array $data = [],
		array $files = []
		): Extractor {
			return new class($query, $data, $files) extends Extractor {
				public function __construct(array $query, array $data, array $files) {
					// Don't call parent - we're manually setting protected properties
					$this->parameters = $query;
					$this->values     = $data;
					$this->files      = $files;
				}

				protected function initialize(): void {
					// Already initialized in constructor
				}
			};
	}

	// ========================================================================
	// Request Type Detection
	// ========================================================================

	public function testDetectsGetRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Get, $request->getType());
	}

	public function testDetectsPostRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Post, $request->getType());
	}

	public function testDetectsPutRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'PUT';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Put, $request->getType());
	}

	public function testDetectsPatchRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Patch, $request->getType());
	}

	public function testDetectsDeleteRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'DELETE';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Delete, $request->getType());
	}

	public function testDetectsHeadRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Head, $request->getType());
	}

	public function testDetectsOptionsRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
		$request = new HttpRequest();
		$this->assertSame(RequestType::Options, $request->getType());
	}

	public function testDefaultsToGetWhenMethodMissing(): void {
		unset($_SERVER['REQUEST_METHOD']);
		$request = new HttpRequest();
		$this->assertSame(RequestType::Get, $request->getType());
	}

	public function testGetMethodReturnsString(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$request = new HttpRequest();
		$this->assertSame('POST', $request->getMethod());
	}

	// ========================================================================
	// Query/Data Extraction via Mock Extractor
	// ========================================================================

	public function testExtractsQueryParameters(): void {
		$query = ['name' => 'John', 'age' => '25'];
		$extractor = $this->createMockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame($query, $request->getQuery());
	}

	public function testExtractsDataParameters(): void {
		$data = ['email' => 'john@example.com', 'password' => 'secret'];
		$extractor = $this->createMockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame($data, $request->getData());
	}

	public function testMergesQueryAndDataWithPostOverride(): void {
		$query = ['name' => 'John', 'age' => '25'];
		$data  = ['name' => 'Jane', 'email' => 'jane@example.com'];
		$extractor = $this->createMockExtractor($query, $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame('Jane', $request->get('name'));
		$this->assertSame('25', $request->get('age'));
		$this->assertSame('jane@example.com', $request->get('email'));
	}

	public function testGetReturnsNonStringValuesDirectly(): void {
		$query = ['tags' => ['php', 'testing'], 'settings' => ['debug' => true]];
		$extractor = $this->createMockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame(['php', 'testing'], $request->get('tags'));
		$this->assertSame(['debug' => true], $request->get('settings'));
	}

	public function testGetFromQueryWithTypeCasting(): void {
		$query = ['age' => '25', 'active' => 'true', 'score' => '98.5'];
		$extractor = $this->createMockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame(25, $request->getFromQuery('age', 0));
		$this->assertSame(true, $request->getFromQuery('active', false));
		$this->assertSame(98.5, $request->getFromQuery('score', 0.0));
	}

	public function testGetFromQueryReturnsDefaultWhenMissing(): void {
		$extractor = $this->createMockExtractor([]);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame('default', $request->getFromQuery('missing', 'default'));
		$this->assertNull($request->getFromQuery('missing'));
	}

	public function testGetFromQueryReturnsNonStringValueDirectly(): void {
		$query = ['tags' => ['php', 'testing'], 'count' => 42];
		$extractor = $this->createMockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame(['php', 'testing'], $request->getFromQuery('tags'));
		$this->assertSame(42, $request->getFromQuery('count'));
	}

	public function testGetFromDataWithTypeCasting(): void {
		$data = ['quantity' => '10', 'verified' => 'false', 'price' => '19.99'];
		$extractor = $this->createMockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame(10, $request->getFromData('quantity', 0));
		$this->assertSame(false, $request->getFromData('verified', true));
		$this->assertSame(19.99, $request->getFromData('price', 0.0));
	}

	public function testGetFromDataReturnsDefaultWhenMissing(): void {
		$extractor = $this->createMockExtractor();
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame(999, $request->getFromData('missing', 999));
		$this->assertNull($request->getFromData('missing'));
	}

	public function testGetFromDataReturnsNonStringValueDirectly(): void {
		$data = ['items' => ['item1', 'item2'], 'total' => 100];
		$extractor = $this->createMockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame(['item1', 'item2'], $request->getFromData('items'));
		$this->assertSame(100, $request->getFromData('total'));
	}

	public function testHasQueryChecksExistence(): void {
		$query = ['name' => 'John'];
		$extractor = $this->createMockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertTrue($request->hasQuery('name'));
		$this->assertFalse($request->hasQuery('missing'));
	}

	public function testHasDataChecksExistence(): void {
		$data = ['email' => 'john@example.com'];
		$extractor = $this->createMockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertTrue($request->hasData('email'));
		$this->assertFalse($request->hasData('missing'));
	}

	// ========================================================================
	// File Handling
	// ========================================================================

	public function testGetFilesReturnsAllFiles(): void {
		$mockFile = $this->createMock(UploadedFile::class);
		$files = ['avatar' => $mockFile];
		$extractor = $this->createMockExtractor([], [], $files);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$this->assertSame($files, $request->getFiles());
	}

	public function testGetFilesReturnsMixedStructure(): void {
		$mockFile1 = $this->createMock(UploadedFile::class);
		$mockFile2 = $this->createMock(UploadedFile::class);
		$files = [
			'avatar' => $mockFile1,
			'docs' => [$mockFile2],
		];
		$extractor = $this->createMockExtractor([], [], $files);
		HttpRequest::setDefaultExtractor($extractor);
		$request = new HttpRequest();
		$result = $request->getFiles();
		$this->assertSame($mockFile1, $result['avatar']);
		$this->assertIsArray($result['docs']);
		$this->assertSame($mockFile2, $result['docs'][0]);
	}

	// ========================================================================
	// Protocol Detection
	// ========================================================================

	public function testDetectsHttpsFromServerHttps(): void {
		$_SERVER['HTTPS'] = 'on';
		$request = new HttpRequest();
		$this->assertSame('https', $request->getProtocol());
	}

	public function testDetectsHttpsFromServerHttpsValue1(): void {
		$_SERVER['HTTPS'] = '1';
		$request = new HttpRequest();
		$this->assertSame('https', $request->getProtocol());
	}

	public function testDetectsHttpsFromForwardedProto(): void {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		$request = new HttpRequest();
		$this->assertSame('https', $request->getProtocol());
	}

	public function testDetectsHttpsFromPort443(): void {
		$_SERVER['SERVER_PORT'] = '443';
		$request = new HttpRequest();
		$this->assertSame('https', $request->getProtocol());
	}

	public function testDefaultsToHttp(): void {
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
		$_SERVER['SERVER_PORT'] = '80';
		$request = new HttpRequest();
		$this->assertSame('http', $request->getProtocol());
	}

	// ========================================================================
	// Remote IP Detection
	// ========================================================================

	public function testDetectsIpFromCloudflare(): void {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';
		$request = new HttpRequest();
		$this->assertSame('203.0.113.1', $request->getRemoteIp());
	}

	public function testDetectsIpFromXForwardedFor(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2, 198.51.100.1';
		$request = new HttpRequest();
		$this->assertSame('203.0.113.2', $request->getRemoteIp());
	}

	public function testDetectsIpFromXRealIp(): void {
		$_SERVER['HTTP_X_REAL_IP'] = '203.0.113.3';
		$request = new HttpRequest();
		$this->assertSame('203.0.113.3', $request->getRemoteIp());
	}

	public function testDetectsIpFromRemoteAddr(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.4';
		$request = new HttpRequest();
		$this->assertSame('203.0.113.4', $request->getRemoteIp());
	}

	public function testAcceptsPrivateIps(): void {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
		$request = new HttpRequest();
		$this->assertSame('192.168.1.1', $request->getRemoteIp());
	}

	public function testReturnsEmptyStringForInvalidIp(): void {
		$_SERVER['REMOTE_ADDR'] = 'invalid-ip';
		$request = new HttpRequest();
		$this->assertSame('', $request->getRemoteIp());
	}

	// ========================================================================
	// URL/URI/Path Distinction
	// ========================================================================

	public function testGetUrlExcludesQueryString(): void {
		$_SERVER['HTTP_HOST'] = 'example.com';
		$_SERVER['REQUEST_URI'] = '/path/to/resource?param=value';
		$request = new HttpRequest();
		$this->assertSame('http://example.com/path/to/resource', $request->getUrl());
	}

	public function testGetUriIncludesQueryString(): void {
		$_SERVER['REQUEST_URI'] = '/path?param=value';
		$request = new HttpRequest();
		$this->assertSame('/path?param=value', $request->getUri());
	}

	public function testGetPathExcludesQueryString(): void {
		$_SERVER['REQUEST_URI'] = '/path/to/resource?param=value';
		$request = new HttpRequest();
		$this->assertSame('/path/to/resource', $request->getPath());
	}

	public function testGetHostIncludesNonStandardPort(): void {
		$_SERVER['HTTP_HOST'] = 'example.com:8080';
		$request = new HttpRequest();
		$this->assertSame('example.com:8080', $request->getHost());
	}

	public function testGetHostWithStandardPort(): void {
		$_SERVER['HTTP_HOST'] = 'example.com';
		$request = new HttpRequest();
		$this->assertSame('example.com', $request->getHost());
	}

	public function testGetReferer(): void {
		$_SERVER['HTTP_REFERER'] = 'https://previous.com/page';
		$request = new HttpRequest();
		$this->assertSame('https://previous.com/page', $request->getReferer());
	}

	public function testGetRefererReturnsEmptyWhenMissing(): void {
		unset($_SERVER['HTTP_REFERER']);
		$request = new HttpRequest();
		$this->assertSame('', $request->getReferer());
	}

	// ========================================================================
	// Header Access
	// ========================================================================

	public function testExtractsHttpHeaders(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
		$request = new HttpRequest();
		$this->assertSame('application/json', $request->getHeader('Accept'));
		$this->assertSame('TestAgent/1.0', $request->getHeader('User-Agent'));
	}

	public function testExtractsContentTypeHeader(): void {
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$request = new HttpRequest();
		$this->assertSame('application/json', $request->getHeader('Content-Type'));
	}

	public function testGetHeaderIsCaseInsensitive(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$request = new HttpRequest();
		$this->assertSame('text/html', $request->getHeader('accept'));
		$this->assertSame('text/html', $request->getHeader('ACCEPT'));
		$this->assertSame('text/html', $request->getHeader('Accept'));
	}

	public function testGetHeaderReturnsNullWhenMissing(): void {
		$request = new HttpRequest();
		$this->assertNull($request->getHeader('X-Custom-Header'));
	}

	public function testGetHeadersExtractsAllHttpHeaders(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_SERVER['CONTENT_LENGTH'] = '1024';
		$_SERVER['OTHER_VAR'] = 'ignored';
		$request = new HttpRequest();
		$headers = $request->getHeaders();
		$this->assertArrayHasKey('Accept', $headers);
		$this->assertArrayHasKey('User-Agent', $headers);
		$this->assertArrayHasKey('Authorization', $headers);
		$this->assertArrayHasKey('Content-Type', $headers);
		$this->assertArrayHasKey('Content-Length', $headers);
		$this->assertArrayNotHasKey('Other-Var', $headers);
		$this->assertSame('application/json', $headers['Accept']);
		$this->assertSame('1024', $headers['Content-Length']);
	}

	// ========================================================================
	// Format Detection (wants)
	// ========================================================================

	public function testWantsDetectsJson(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$request = new HttpRequest();
		$this->assertSame('json', $request->wants());
	}

	public function testWantsDetectsXml(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/xml';
		$request = new HttpRequest();
		$this->assertSame('xml', $request->wants());
	}

	public function testWantsDetectsText(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/plain';
		$request = new HttpRequest();
		$this->assertSame('txt', $request->wants());
	}

	public function testWantsDefaultsToHtml(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$request = new HttpRequest();
		$this->assertSame('html', $request->wants());
	}

	// ========================================================================
	// AJAX Detection
	// ========================================================================

	public function testIsAjaxDetectsXmlHttpRequest(): void {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		$request = new HttpRequest();
		$this->assertTrue($request->isAjax());
	}

	public function testIsAjaxIsCaseInsensitive(): void {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
		$request = new HttpRequest();
		$this->assertTrue($request->isAjax());
	}

	public function testIsAjaxReturnsFalseWhenHeaderMissing(): void {
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		$request = new HttpRequest();
		$this->assertFalse($request->isAjax());
	}
}
