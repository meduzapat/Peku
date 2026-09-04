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

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Peku\Abstractions\{Collection, MutableCollection};
use Peku\Messages\MessageFactory;
use Peku\Messages\Requestable;
use Peku\Messages\RequestType;
use Peku\Messages\Http\{HttpRequest, HttpResponse, AllowedHost, TrustedHosts};
use Peku\Helpers\Http\Extractors\Normal;
use Peku\Validation\UntrustedRequestException;

/**
 * Unit tests for MessageFactory
 */
class MessageFactoryTest extends TestCase {

	use PHPMock;

	protected function tearDown(): void {
		MessageFactory::clearMappings();
		parent::tearDown();
	}

	/**
	 * Minimal Requestable so the factory can be exercised without superglobals
	 */
	private function request(string $wants): Requestable {
		return new class ($wants) implements Requestable {

			public function __construct(private string $wants) {}

			public function getType(): RequestType {
				return RequestType::Get;
			}

			public function values(): Collection {
				return new Collection();
			}

			public function wants(): string {
				return $this->wants;
			}
		};
	}

	/*************************
	 * createRequest() Tests *
	 *************************/

	private function permissiveRules(string $host = 'peku.dev'): MutableCollection {
		return (new MutableCollection())->set('allowedHost', new AllowedHost(TrustedHosts::only($host)));
	}

	/**
	 * Sets up the environment for the HTTP branch of createRequest(): mocks
	 * php_sapi_name() to report non-CLI, and (re)installs the real extractor
	 * after $_SERVER is populated - Normal::initialize() snapshots $_SERVER at
	 * construction, and Request::$extractor is static, so a mock extractor
	 * left behind by an unrelated test elsewhere in the suite would otherwise
	 * still be active here.
	 */
	private function prepareHttpEnvironment(string $host = 'peku.dev'): void {
		$this->getFunctionMock('Peku\\Messages', 'php_sapi_name')
			->expects($this->once())
			->willReturn('fpm-fcgi');

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['HTTP_ACCEPT']    = 'text/html';
		$_SERVER['HTTP_HOST']      = $host;
		HttpRequest::setDefaultExtractor(new Normal());
	}

	public function testCreateRequestBuildsHttpRequestOutsideCli(): void {
		$this->prepareHttpEnvironment();

		$request = MessageFactory::createRequest($this->permissiveRules());

		$this->assertInstanceOf(HttpRequest::class, $request);
	}

	public function testCreateRequestDefaultsToHttpRequestsFailClosedRules(): void {
		$this->prepareHttpEnvironment();

		// No $rules argument - proves the parameter's default (null) really
		// reaches HttpRequest rather than MessageFactory silently supplying
		// something permissive of its own.
		$this->expectException(UntrustedRequestException::class);
		$this->expectExceptionMessage('No trusted hosts configured');
		MessageFactory::createRequest();
	}

	public function testCreateRequestPassesGivenRulesThrough(): void {
		$this->prepareHttpEnvironment('peku.dev');

		/** @var HttpRequest $request */
		$request = MessageFactory::createRequest($this->permissiveRules('peku.dev'));

		$this->assertSame('peku.dev', $request->getHost());
	}

	public function testCreateRequestEnforcesTheRulesItWasGiven(): void {
		$this->prepareHttpEnvironment('evil.com');

		// The allowlist here does not match the request's actual host - proves
		// the rules passed in are the ones enforced, not a default substituted
		// silently in their place.
		$this->expectException(UntrustedRequestException::class);
		$this->expectExceptionMessage("Host 'evil.com' is not in the trusted allowlist");
		MessageFactory::createRequest($this->permissiveRules('peku.dev'));
	}

	/**************************
	 * createResponse() Tests *
	 **************************/

	public function testCreateResponseReturnsConfiguredResponse(): void {
		$response = MessageFactory::createResponse($this->request('text/html'), 'body', 201);

		$this->assertInstanceOf(HttpResponse::class, $response);
		$this->assertSame('body', $response->getContent());
		$this->assertSame(201, $response->getCode());
	}

	public function testCreateResponseSetsContentTypeFromNegotiatedMime(): void {
		$response = MessageFactory::createResponse($this->request('application/json'));

		$this->assertSame(
			'application/json; charset=utf-8',
			$response->headers()->get('Content-Type')
		);
	}

	public function testCreateResponseFallsBackForUnmappedMime(): void {
		$response = MessageFactory::createResponse($this->request('application/vnd.made-up'));

		$this->assertInstanceOf(HttpResponse::class, $response);
		$this->assertSame(
			'application/vnd.made-up; charset=utf-8',
			$response->headers()->get('Content-Type')
		);
	}

	public function testCreateResponseUsesRegisteredCustomMapping(): void {
		MessageFactory::registerMapping('text/html', CustomResponse::class);

		$this->assertInstanceOf(
			CustomResponse::class,
			MessageFactory::createResponse($this->request('text/html'))
		);
	}

	/*****************
	 * Mapping Tests *
	 *****************/

	public function testGetResponseClassPrefersCustomOverDefault(): void {
		$this->assertSame(HttpResponse::class, MessageFactory::getResponseClass('text/html'));

		MessageFactory::registerMapping('text/html', CustomResponse::class);
		$this->assertSame(CustomResponse::class, MessageFactory::getResponseClass('text/html'));
	}

	public function testUnregisterMappingRevertsToDefault(): void {
		MessageFactory::registerMapping('text/html', CustomResponse::class);
		MessageFactory::unregisterMapping('text/html');

		$this->assertSame(HttpResponse::class, MessageFactory::getResponseClass('text/html'));
	}

	public function testGetResponseClassFallsBackToHttpResponse(): void {
		$this->assertSame(HttpResponse::class, MessageFactory::getResponseClass('application/vnd.unknown'));
	}
}

/**
 * Response used to verify custom mapping selection
 */
class CustomResponse extends HttpResponse {}