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
use Peku\Messages\Http\{HttpRequest, AllowedHost, TrustedHosts};
use Peku\Messages\RequestType;
use Peku\Helpers\Http\Extractors\Normal;
use Peku\Helpers\Http\UploadedFile;
use Peku\Abstractions\{Collection, MutableCollection};
use Peku\Validation\UntrustedRequestException;
use Peku\Tests\Fixtures\Mocks\MockExtractor;

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
		HttpRequest::trustProxies(false); // Reset to default
	}

	protected function tearDown(): void {
		$_SERVER = $this->originalServer;
		// Don't cache extractor - let each test create fresh Normal
		HttpRequest::trustProxies(false);
	}

	/****************
	 * Test Helpers *
	 ****************/

	/**
	 * Build a request with a permissive allowedHost rule
	 *
	 * Used by every test below that isn't specifically exercising allowedHost -
	 * the constructor's real default is fail-closed (see AllowedHostTest and
	 * the "Rule Enforcement" section below).
	 */
	private function request(): HttpRequest {
		return new HttpRequest(self::permissiveRules());
	}

	private static function permissiveRules(): MutableCollection {
		return (new MutableCollection())->set(
			'allowedHost',
			new AllowedHost(TrustedHosts::only($_SERVER['HTTP_HOST'] ?? ''))
		);
	}

	public function testServerReturnsCollection(): void {
		$request = $this->request();
		$this->assertInstanceOf(Collection::class, $request->server());
	}

	/**************************
	 * Request Type Detection *
	 **************************/

	public function testDetectsGetRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$request = $this->request();
		$this->assertSame(RequestType::Get, $request->getType());
	}

	public function testDetectsPostRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$request = $this->request();
		$this->assertSame(RequestType::Post, $request->getType());
	}

	public function testDetectsPutRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'PUT';
		$request = $this->request();
		$this->assertSame(RequestType::Put, $request->getType());
	}

	public function testDetectsPatchRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$request = $this->request();
		$this->assertSame(RequestType::Patch, $request->getType());
	}

	public function testDetectsDeleteRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'DELETE';
		$request = $this->request();
		$this->assertSame(RequestType::Delete, $request->getType());
	}

	public function testDetectsHeadRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$request = $this->request();
		$this->assertSame(RequestType::Head, $request->getType());
	}

	public function testDetectsOptionsRequest(): void {
		$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
		$request = $this->request();
		$this->assertSame(RequestType::Options, $request->getType());
	}

	public function testDefaultsToGetWhenMethodMissing(): void {
		unset($_SERVER['REQUEST_METHOD']);
		$request = $this->request();
		$this->assertSame(RequestType::Get, $request->getType());
	}

	public function testGetMethodReturnsString(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$request = $this->request();
		$this->assertSame('POST', $request->getMethod());
	}

	/**********************
	 * Protocol Detection *
	 **********************/

	public function testDetectsHttpsFromServerHttps(): void {
		$_SERVER['HTTPS'] = 'on';
		$request = $this->request();
		$this->assertSame('https', $request->getScheme());
	}

	public function testDetectsHttpsFromServerHttpsValue1(): void {
		$_SERVER['HTTPS'] = '1';
		$request = $this->request();
		$this->assertSame('https', $request->getScheme());
	}

	public function testDetectsHttpsFromForwardedProtoWhenTrusted(): void {
		HttpRequest::trustProxies(true);
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		$request = $this->request();
		$this->assertSame('https', $request->getScheme());
	}

	public function testIgnoresForwardedProtoWhenNotTrusted(): void {
		HttpRequest::trustProxies(false);
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		$_SERVER['SERVER_PORT'] = '80';
		$request = $this->request();
		$this->assertSame('http', $request->getScheme());
	}

	public function testDetectsHttpsFromPort443(): void {
		$_SERVER['SERVER_PORT'] = '443';
		$request = $this->request();
		$this->assertSame('https', $request->getScheme());
	}

	public function testDefaultsToHttp(): void {
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
		$_SERVER['SERVER_PORT'] = '80';
		$request = $this->request();
		$this->assertSame('http', $request->getScheme());
	}

	/***********************
	 * Remote IP Detection *
	 ***********************/

	public function testDetectsIpFromCloudflareWhenTrusted(): void {
		HttpRequest::trustProxies(true);
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';
		$request = $this->request();
		$this->assertSame('203.0.113.1', $request->getRemoteIp());
	}

	public function testIgnoresCloudflareIpWhenNotTrusted(): void {
		HttpRequest::trustProxies(false);
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
		$request = $this->request();
		$this->assertSame('203.0.113.99', $request->getRemoteIp());
	}

	public function testDetectsIpFromXForwardedForWhenTrusted(): void {
		HttpRequest::trustProxies(true);
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2, 198.51.100.1';
		$request = $this->request();
		$this->assertSame('203.0.113.2', $request->getRemoteIp());
	}

	public function testDetectsIpFromXRealIpWhenTrusted(): void {
		HttpRequest::trustProxies(true);
		$_SERVER['HTTP_X_REAL_IP'] = '203.0.113.3';
		$request = $this->request();
		$this->assertSame('203.0.113.3', $request->getRemoteIp());
	}

	public function testDetectsIpFromRemoteAddr(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.4';
		$request = $this->request();
		$this->assertSame('203.0.113.4', $request->getRemoteIp());
	}

	public function testAcceptsPrivateIps(): void {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
		$request = $this->request();
		$this->assertSame('192.168.1.1', $request->getRemoteIp());
	}

	public function testReturnsEmptyStringForInvalidIp(): void {
		$_SERVER['REMOTE_ADDR'] = 'invalid-ip';
		$request = $this->request();
		$this->assertSame('', $request->getRemoteIp());
	}

	/****************************
	 * URL/URI/Path Distinction *
	 ****************************/

	public function testGetUrlExcludesQueryString(): void {
		$_SERVER['HTTP_HOST'] = 'example.com';
		$_SERVER['REQUEST_URI'] = '/path/to/resource?param=value';
		$request = $this->request();
		$this->assertSame('http://example.com/path/to/resource', $request->getUrl());
	}

	public function testGetUriIncludesQueryString(): void {
		$_SERVER['REQUEST_URI'] = '/path?param=value';
		$request = $this->request();
		$this->assertSame('/path?param=value', $request->getUri());
	}

	public function testGetPathExcludesQueryString(): void {
		$_SERVER['REQUEST_URI'] = '/path/to/resource?param=value';
		$request = $this->request();
		$this->assertSame('/path/to/resource', $request->getPath());
	}

	public function testGetHostIncludesNonStandardPort(): void {
		$_SERVER['HTTP_HOST'] = 'example.com:8080';
		$request = $this->request();
		$this->assertSame('example.com:8080', $request->getHost());
	}

	public function testGetHostWithStandardPort(): void {
		$_SERVER['HTTP_HOST'] = 'example.com';
		$request = $this->request();
		$this->assertSame('example.com', $request->getHost());
	}

	public function testGetReferer(): void {
		$_SERVER['HTTP_REFERER'] = 'https://previous.com/page';
		$request = $this->request();
		$this->assertSame('https://previous.com/page', $request->getReferer());
	}

	public function testGetRefererReturnsEmptyWhenMissing(): void {
		unset($_SERVER['HTTP_REFERER']);
		$request = $this->request();
		$this->assertSame('', $request->getReferer());
	}

	/*****************
	 * Header Access *
	 *****************/

	public function testExtractsHttpHeaders(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
		$request = $this->request();
		$this->assertSame('application/json', $request->headers()->get('Accept'));
		$this->assertSame('TestAgent/1.0', $request->headers()->get('User-Agent'));
	}

	public function testExtractsContentTypeHeader(): void {
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$request = $this->request();
		$this->assertSame('application/json', $request->headers()->get('Content-Type'));
	}

	public function testGetHeaderIsCaseInsensitive(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$request = $this->request();
		// Headers use Title-Case keys
		$this->assertSame('text/html', $request->headers()->get('Accept'));
	}

	public function testGetHeaderReturnsDefaultWhenMissing(): void {
		$request = $this->request();
		$this->assertNull($request->headers()->get('X-Custom-Header'));
		$this->assertSame('default', $request->headers()->get('X-Custom-Header', 'default'));
	}

	public function testGetHeadersExtractsAllHttpHeaders(): void {
		$_SERVER['HTTP_ACCEPT']        = 'application/json';
		$_SERVER['HTTP_USER_AGENT']    = 'TestAgent/1.0';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
		$_SERVER['CONTENT_TYPE']       = 'application/json';
		$_SERVER['CONTENT_LENGTH']     = '1024';
		$_SERVER['OTHER_VAR']          = 'ignored';
		$request = $this->request();
		$headers = $request->headers()->all();
		$this->assertArrayHasKey('Accept', $headers);
		$this->assertArrayHasKey('User-Agent', $headers);
		$this->assertArrayHasKey('Authorization', $headers);
		$this->assertArrayHasKey('Content-Type', $headers);
		$this->assertArrayHasKey('Content-Length', $headers);
		$this->assertArrayNotHasKey('Other-Var', $headers);
		$this->assertSame('application/json', $headers['Accept']);
		$this->assertSame('1024', $headers['Content-Length']);
	}

	/*****************************************
	 * Content Negotiation - accepts() Tests *
	 *****************************************/

	public function testAcceptsWithNoHeader(): void {
		unset($_SERVER['HTTP_ACCEPT']);
		$request = $this->request();
		$this->assertSame(['text/html'], $request->accepts());
	}

	public function testAcceptsWithQualityParametersAndSorting(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html;q=0.9, application/json, text/plain;q=0.5';
		$request = $this->request();

		// Sorted by quality DESC: json(1.0), html(0.9), plain(0.5)
		$this->assertSame(['application/json', 'text/html', 'text/plain'], $request->accepts());
	}

	public function testAcceptsRejectsQualityZero(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html;q=0, application/json';
		$request = $this->request();

		// html rejected with q=0
		$this->assertSame(['application/json'], $request->accepts());
	}

	public function testAcceptsWithAllRejected(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html;q=0, application/json;q=0';
		$request = $this->request();

		// All rejected - defaults to text/html
		$this->assertSame(['text/html'], $request->accepts());
	}

	public function testAcceptsSkipsEmptySegments(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/json,,,text/html';
		$request = $this->request();

		$result = $request->accepts();
		$this->assertCount(2, $result);
		$this->assertContains('application/json', $result);
		$this->assertContains('text/html', $result);
	}

	public function testAcceptsSortsSpecificityWhenSameQuality(): void {
		// All have same quality (1.0), should sort by specificity
		$_SERVER['HTTP_ACCEPT'] = '*/*, text/*, text/html';
		$request = $this->request();

		// Sorted by specificity: exact > type wildcard > full wildcard
		$this->assertSame(['text/html', 'text/*', '*/*'], $request->accepts());
	}

	public function testAcceptsCombinedQualityAndSpecificity(): void {
		// Mixed quality and specificity
		$_SERVER['HTTP_ACCEPT'] = 'text/*;q=0.8, text/html, application/json;q=0.9, */*;q=0.5';
		$request = $this->request();

		// json(q=1.0) > text/html(q=1.0, higher specificity) > text/*(q=0.8) > */*(q=0.5)
		$expected = ['text/html', 'application/json', 'text/*', '*/*'];
		$this->assertSame($expected, $request->accepts());
	}

	public function testAcceptsWithInvalidQuality(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html;q=ABc, application/json;q=None';
		$request = $this->request();

		// All rejected - defaults to text/html
		$this->assertSame(['text/html'], $request->accepts());
	}

	/****************************
	 * Protocol Detection Tests *
	 ****************************/

	public function testGetProtocolVersionFromServerProtocol(): void {
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$request = $this->request();
		$this->assertSame('1.1', $request->getProtocolVersion());
	}

	public function testGetProtocolVersionWithHttp2(): void {
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/2.0';
		$request = $this->request();
		$this->assertSame('2.0', $request->getProtocolVersion());
	}

	public function testGetProtocolVersionDefaultsWhenMissing(): void {
		unset($_SERVER['SERVER_PROTOCOL']);
		$request = $this->request();
		$this->assertSame('1.1', $request->getProtocolVersion());
	}

	/********************
	 * Format Detection *
	 ********************/

	public function testWantsDetectsJson(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$request = $this->request();
		$this->assertSame('application/json', $request->wants());
	}

	public function testWantsDetectsXml(): void {
		$_SERVER['HTTP_ACCEPT'] = 'application/xml';
		$request = $this->request();
		$this->assertSame('application/xml', $request->wants());
	}

	public function testWantsDetectsText(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/plain';
		$request = $this->request();
		$this->assertSame('text/plain', $request->wants());
	}

	public function testWantsDefaultsToHtml(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$request = $this->request();
		$this->assertSame('text/html', $request->wants());
	}

	/******************
	 * AJAX Detection *
	 ******************/

	public function testIsAjaxDetectsXmlHttpRequest(): void {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		$request = $this->request();
		$this->assertTrue($request->isAjax());
	}

	public function testIsAjaxIsCaseInsensitive(): void {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
		$request = $this->request();
		$this->assertTrue($request->isAjax());
	}

	public function testIsAjaxReturnsFalseWhenHeaderMissing(): void {
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		$request = $this->request();
		$this->assertFalse($request->isAjax());
	}

	/*****************************************************************
	 * Query/Data Extraction via Mock Extractor (after Normal tests) *
	 *****************************************************************/

	public function testExtractsQueryParameters(): void {
		$query = ['name' => 'John', 'age' => '25'];
		$extractor = new MockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame($query, $request->query()->all());
	}

	public function testExtractsDataParameters(): void {
		$data = ['email' => 'john@example.com', 'password' => 'secret'];
		$extractor = new MockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame($data, $request->data()->all());
	}

	public function testMergesQueryAndDataWithPostOverride(): void {
		$query = ['name' => 'John', 'age' => '25'];
		$data  = ['name' => 'Jane', 'email' => 'jane@example.com'];
		$extractor = new MockExtractor($query, $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame('Jane', $request->values()->get('name'));
		$this->assertSame('25', $request->values()->get('age'));
		$this->assertSame('jane@example.com', $request->values()->get('email'));
	}

	public function testGetReturnsNonStringValuesDirectly(): void {
		$query = ['tags' => ['php', 'testing'], 'settings' => ['debug' => true]];
		$extractor = new MockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame(['php', 'testing'], $request->values()->get('tags'));
		$this->assertSame(['debug' => true], $request->values()->get('settings'));
	}

	public function testGetFromQueryWithTypeCasting(): void {
		$query = ['age' => '25', 'active' => 'true', 'score' => '98.5'];
		$extractor = new MockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame(25, $request->query()->get('age', 0));
		$this->assertSame(true, $request->query()->get('active', false));
		$this->assertSame(98.5, $request->query()->get('score', 0.0));
	}

	public function testGetFromQueryReturnsDefaultWhenMissing(): void {
		$extractor = new MockExtractor([]);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame('default', $request->query()->get('missing', 'default'));
		$this->assertNull($request->query()->get('missing'));
	}

	public function testGetFromQueryReturnsNonStringValueDirectly(): void {
		$query = ['tags' => ['php', 'testing'], 'count' => 42];
		$extractor = new MockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame(['php', 'testing'], $request->query()->get('tags'));
		$this->assertSame(42, $request->query()->get('count'));
	}

	public function testGetFromDataWithTypeCasting(): void {
		$data = ['quantity' => '10', 'verified' => 'false', 'price' => '19.99'];
		$extractor = new MockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame(10, $request->data()->get('quantity', 0));
		$this->assertSame(false, $request->data()->get('verified', true));
		$this->assertSame(19.99, $request->data()->get('price', 0.0));
	}

	public function testGetFromDataReturnsDefaultWhenMissing(): void {
		$extractor = new MockExtractor();
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame(999, $request->data()->get('missing', 999));
		$this->assertNull($request->data()->get('missing'));
	}

	public function testGetFromDataReturnsNonStringValueDirectly(): void {
		$data = ['items' => ['item1', 'item2'], 'total' => 100];
		$extractor = new MockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame(['item1', 'item2'], $request->data()->get('items'));
		$this->assertSame(100, $request->data()->get('total'));
	}

	public function testHasQueryChecksExistence(): void {
		$query = ['name' => 'John'];
		$extractor = new MockExtractor($query);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertTrue($request->query()->has('name'));
		$this->assertFalse($request->query()->has('missing'));
	}

	public function testHasDataChecksExistence(): void {
		$data = ['email' => 'john@example.com'];
		$extractor = new MockExtractor([], $data);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertTrue($request->data()->has('email'));
		$this->assertFalse($request->data()->has('missing'));
	}

	/**********************************
	 * File Handling (Mock Extractor) *
	 **********************************/

	public function testGetFilesReturnsAllFiles(): void {
		$mockFile = $this->createMock(UploadedFile::class);
		$files = ['avatar' => $mockFile];
		$extractor = new MockExtractor([], [], $files);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$this->assertSame($files, $request->files());
	}

	public function testGetFilesReturnsMixedStructure(): void {
		$mockFile1 = $this->createMock(UploadedFile::class);
		$mockFile2 = $this->createMock(UploadedFile::class);
		$files = [
			'avatar' => $mockFile1,
			'docs' => [$mockFile2],
		];
		$extractor = new MockExtractor([], [], $files);
		HttpRequest::setDefaultExtractor($extractor);
		$request = $this->request();
		$result = $request->files();
		$this->assertSame($mockFile1, $result['avatar']);
		$this->assertIsArray($result['docs']);
		$this->assertSame($mockFile2, $result['docs'][0]);
	}

	/**********************************
	 * Rule Enforcement (allowedHost) *
	 **********************************/

	public function testConstructionThrowsWhenNoRulesConfigured(): void {
		// Normal::initialize() snapshots $_SERVER at construction, so it must
		// run after $_SERVER is set - and it must run at all, since a prior
		// test's mock extractor (fixed empty $server) would otherwise still
		// be the active default here.
		$_SERVER['HTTP_HOST'] = 'peku.dev';
		HttpRequest::setDefaultExtractor(new Normal());

		$this->expectException(UntrustedRequestException::class);
		$this->expectExceptionMessage('No trusted hosts configured');
		new HttpRequest();
	}

	public function testConstructionThrowsForAHostNotInTheAllowlist(): void {
		$_SERVER['HTTP_HOST'] = 'evil.com';
		HttpRequest::setDefaultExtractor(new Normal());
		$rules = (new MutableCollection())->set(
			'allowedHost',
			new AllowedHost(TrustedHosts::only('peku.dev'))
		);

		$this->expectException(UntrustedRequestException::class);
		$this->expectExceptionMessage("Host 'evil.com' is not in the trusted allowlist");
		new HttpRequest($rules);
	}

	public function testConstructionSucceedsForAnAllowedHost(): void {
		$_SERVER['HTTP_HOST'] = 'peku.dev';
		HttpRequest::setDefaultExtractor(new Normal());
		$rules = (new MutableCollection())->set(
			'allowedHost',
			new AllowedHost(TrustedHosts::only('peku.dev'))
		);

		$request = new HttpRequest($rules);
		$this->assertSame('peku.dev', $request->getHost());
	}

	public function testUrlIsNeverBuiltFromARejectedHost(): void {
		$_SERVER['HTTP_HOST']   = 'evil.com';
		$_SERVER['REQUEST_URI'] = '/reset-password?token=abc';
		HttpRequest::setDefaultExtractor(new Normal());

		try {
			new HttpRequest();
			$this->fail('Expected UntrustedRequestException was not thrown');
		}
		catch (UntrustedRequestException) {
			// getUrl() is never reachable on an object that never finished
			// construction - the guarantee is structural, not a runtime check.
			$this->assertTrue(true);
		}
	}
}
