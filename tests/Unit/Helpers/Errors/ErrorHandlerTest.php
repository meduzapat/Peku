<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Tests\Unit\Helpers\Errors;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Peku\Helpers\Errors\ErrorHandler;
use Peku\Helpers\Loggers\Loggeable;
use Peku\Helpers\Loggers\LogLevel;

/**
 * @backupGlobals disabled
 */
class ErrorHandlerTest extends TestCase {

	use PHPMock;

	private Loggeable $mockLogger;

	protected function setUp(): void {
		$this->mockLogger = $this->createMock(Loggeable::class);
	}

	protected function tearDown(): void {
		restore_error_handler();
		restore_exception_handler();
		parent::tearDown();
	}

	// ========================================================================
	// initialize() Tests
	// ========================================================================

	public function testInitializeWithLogger(): void {

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->stringContains('Warning'),
				LogLevel::Warning
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleError(E_WARNING, 'Test warning', __FILE__, __LINE__);
	}

	public function testInitializeWithoutLoggerUsesNoop(): void {
		$this->expectNotToPerformAssertions();
		ErrorHandler::initialize();
		ErrorHandler::handleError(E_WARNING, 'Test warning', __FILE__, __LINE__);
	}

	// ========================================================================
	// handleError() Tests - Error Type Mapping
	// ========================================================================

	/**
	 * @dataProvider errorTypeProvider
	 */
	public function testHandleErrorMapsCorrectErrorType(int $errno, string $expectedType): void {

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->stringContains($expectedType),
				$this->anything()
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleError($errno, 'Test message', __FILE__, __LINE__);
	}

	public static function errorTypeProvider(): array {
		return [
			'E_ERROR'             => [E_ERROR,             'Error'],
			'E_WARNING'           => [E_WARNING,           'Warning'],
			'E_PARSE'             => [E_PARSE,             'Parse Error'],
			'E_NOTICE'            => [E_NOTICE,            'Notice'],
			'E_CORE_ERROR'        => [E_CORE_ERROR,        'Core Error'],
			'E_CORE_WARNING'      => [E_CORE_WARNING,      'Core Warning'],
			'E_COMPILE_ERROR'     => [E_COMPILE_ERROR,     'Compile Error'],
			'E_COMPILE_WARNING'   => [E_COMPILE_WARNING,   'Compile Warning'],
			'E_USER_ERROR'        => [E_USER_ERROR,        'User Error'],
			'E_USER_WARNING'      => [E_USER_WARNING,      'User Warning'],
			'E_USER_NOTICE'       => [E_USER_NOTICE,       'User Notice'],
			'E_STRICT'            => [E_STRICT,            'Runtime Notice'],
			'E_RECOVERABLE_ERROR' => [E_RECOVERABLE_ERROR, 'Catchable Fatal Error'],
			'E_DEPRECATED'        => [E_DEPRECATED,        'Deprecated'],
			'E_USER_DEPRECATED'   => [E_USER_DEPRECATED,   'User Deprecated'],
		];
	}

	public function testHandleErrorUnknownErrorType(): void {

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->stringContains('Unknown Error'),
				$this->anything()
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleError(99999, 'Test message', __FILE__, __LINE__);
	}

	// ========================================================================
	// handleError() Tests - LogLevel Mapping
	// ========================================================================

	/**
	 * @dataProvider errorLevelProvider
	 */
	public function testHandleErrorMapsToCorrectLogLevel(int $errno, LogLevel $expectedLevel): void {

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->anything(),
				$expectedLevel
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleError($errno, 'Test message', __FILE__, __LINE__);
	}

	public static function errorLevelProvider(): array {
		return [
			'warning level'  => [E_WARNING,           LogLevel::Warning],
			'notice level'   => [E_NOTICE,            LogLevel::Warning],
			'strict level'   => [E_STRICT,            LogLevel::Warning],
			'deprecated'     => [E_DEPRECATED,        LogLevel::Warning],
			'error level'    => [E_ERROR,             LogLevel::Critical],
			'parse level'    => [E_PARSE,             LogLevel::Critical],
			'fatal level'    => [E_RECOVERABLE_ERROR, LogLevel::Critical],
			'unknown level'  => [99999,               LogLevel::Error],
		];
	}

	// ========================================================================
	// handleError() Tests - Message Format
	// ========================================================================

	public function testHandleErrorFormatsMessageCorrectly(): void {

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->matchesRegularExpression('/Warning \[\d+\]: Test message in .+:\d+/'),
				LogLevel::Warning
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleError(E_WARNING, 'Test message', __FILE__, __LINE__);
	}

	public function testHandleErrorIncludesBasename(): void {

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->stringContains('ErrorHandlerTest.php'),
				$this->anything()
			);

		ErrorHandler::initialize($this->mockLogger);
		// Will always return false.
		$this->assertFalse(ErrorHandler::handleError(E_WARNING, 'Test', __FILE__, __LINE__));
	}

	// ========================================================================
	// handleException() Tests
	// ========================================================================

	public function testHandleExceptionLogsWithCriticalLevel(): void {

		$exception = new \RuntimeException('Test exception');

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$exception,
				LogLevel::Critical
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleException($exception);
	}

	public function testHandleExceptionWithDifferentExceptionTypes(): void {

		$exception = new \InvalidArgumentException('Invalid arg');

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$exception,
				LogLevel::Critical
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleException($exception);
	}

	// ========================================================================
	// handleFatal() Tests
	// ========================================================================

	public function testHandleFatalDoesNothingWhenNoError(): void {
		$this->expectNotToPerformAssertions();
		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleFatal();
	}

	/**
	 * @runInSeparateProcess
	 * @dataProvider fatalErrorProvider
	 */
	public function testHandleFatalLogsFatalErrors(int $errorType, string $expectedMessage): void {
		$errorGetLast = $this->getFunctionMock('Peku\\Helpers\\Errors', 'error_get_last');
		$errorGetLast->expects($this->once())->willReturn([
			'type'    => $errorType,
			'message' => $expectedMessage,
			'file'    => __FILE__,
			'line'    => 123,
		]);

		$this->mockLogger
			->expects($this->once())
			->method('log')
			->with(
				$this->stringContains($expectedMessage),
				LogLevel::Critical
			);

		ErrorHandler::initialize($this->mockLogger);
		ErrorHandler::handleFatal();
	}

	public static function fatalErrorProvider(): array {
		return [
			'E_ERROR'         => [E_ERROR,        'Fatal error occurred'],
			'E_PARSE'         => [E_PARSE,        'Parse error occurred'],
			'E_CORE_ERROR'    => [E_CORE_ERROR,   'Core error occurred'],
			'E_COMPILE_ERROR' => [E_COMPILE_ERROR, 'Compile error occurred'],
		];
	}
}
