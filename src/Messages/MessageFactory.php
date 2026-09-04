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

namespace Peku\Messages;

use Peku\Messages\Cli\CliRequest;
use Peku\Messages\Http\HttpRequest;
use Peku\Messages\Http\HttpResponse;
use Peku\Abstractions\MutableCollection;

/**
 * Message factory for creating request and response objects
 *
 * Provides centralized object creation with MIME-based Response class selection.
 */
class MessageFactory {

	/**
	 * Default Response class mappings for MIME types
	 *
	 * @var array<string, class-string<Responseable>>
	 */
	private static array $defaultMappings = [
		'application/json'         => HttpResponse::class,  // Future: JsonResponse
		'application/xml'          => HttpResponse::class,  // Future: XmlResponse
		'text/xml'                 => HttpResponse::class,  // Future: XmlResponse
		'text/html'                => HttpResponse::class,
		'application/xhtml+xml'    => HttpResponse::class,
		'text/plain'               => HttpResponse::class,  // Future: TextResponse
		'text/css'                 => HttpResponse::class,
		'application/javascript'   => HttpResponse::class,
		'text/javascript'          => HttpResponse::class,
		'application/pdf'          => HttpResponse::class,  // Future: PdfResponse
		'image/jpeg'               => HttpResponse::class,  // Future: ImageResponse
		'image/png'                => HttpResponse::class,
		'image/gif'                => HttpResponse::class,
		'image/webp'               => HttpResponse::class,
		'audio/mpeg'               => HttpResponse::class,  // Future: AudioResponse
		'video/mp4'                => HttpResponse::class,  // Future: VideoResponse
		'application/octet-stream' => HttpResponse::class,  // Future: BinaryResponse
	];

	/**
	 * Custom Response class mappings (override defaults)
	 *
	 * @var array<string, class-string<Responseable>>
	 */
	private static array $customMappings = [];

	/**
	 * Create request from current environment
	 *
	 * Auto-detects CLI vs HTTP context.
	 *
	 * @param MutableCollection|null $rules Rules to enforce on the HttpRequest branch
	 *                                      (e.g. allowedHost). Ignored under CLI.
	 *                                      Omit to get HttpRequest's own fail-closed
	 *                                      default.
	 * @TODO: detect other request types.
	 */
	public static function createRequest(?MutableCollection $rules = null): Requestable {
		return php_sapi_name() === 'cli' ? new CliRequest() : new HttpRequest($rules);
	}

	/**
	 * Create response from request with MIME-based content negotiation
	 *
	 * Uses two-tier Response class selection:
	 * 1. Custom mappings (registered via registerMapping)
	 * 2. Default mappings (built-in)
	 * 3. Fallback to HttpResponse if MIME not found
	 *
	 * @param Requestable $request Source request
	 * @param mixed       $content Response content
	 * @param int         $code    HTTP status code
	 * @return Responseable Configured response
	 */
	public static function createResponse(
		Requestable $request,
		mixed $content = '',
		int $code = 200
	): Responseable {
		// The negotiated type selects the class; the response derives its own
		// Content-Type from the request in inquiry().
		$class = self::getResponseClass($request->wants());

		$response = new $class($content, $code);
		$response->inquiry($request);
		return $response;
	}

	/**
	 * Register custom Response class mapping (overrides default)
	 *
	 * Custom mappings take precedence over default mappings.
	 * Use this to provide specialized Response classes for specific MIME types.
	 *
	 * @param string $mime  MIME type (e.g., 'application/json')
	 * @param class-string<Responseable> $class Response class name
	 */
	public static function registerMapping(string $mime, string $class): void {
		self::$customMappings[$mime] = $class;
	}

	/**
	 * Remove custom Response class mapping
	 *
	 * Removes the custom mapping for the specified MIME type.
	 * Subsequent requests will fall back to default mapping.
	 *
	 * @param string $mime MIME type to unregister
	 */
	public static function unregisterMapping(string $mime): void {
		unset(self::$customMappings[$mime]);
	}

	/**
	 * Clear all custom Response class mappings
	 *
	 * Removes all custom mappings, reverting to default mappings only.
	 */
	public static function clearMappings(): void {
		self::$customMappings = [];
	}

	/**
	 * Get registered Response class for MIME type
	 *
	 * Returns custom mapping if set, otherwise default mapping.
	 *
	 * @param string $mime MIME type
	 * @return class-string<Responseable> Response class or null if not found
	 */
	public static function getResponseClass(string $mime): string {
		return
			self::$customMappings[$mime]  ??
			self::$defaultMappings[$mime] ??
			HttpResponse::class;
	}
}
