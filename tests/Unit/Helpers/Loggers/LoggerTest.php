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

namespace Peku\Tests\Unit\Helpers\Loggers;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Loggers\Logger;
use Peku\Helpers\Loggers\LogLevel;

/**
 * Unit tests for Logger abstract class
 */
class LoggerTest extends TestCase {

	private Logger $logger;

	protected function setUp(): void {
		// Create testable concrete implementation
		$this->logger = new class extends Logger {
			public mixed     $lastMessage = null;
			public ?LogLevel $lastLevel   = null;

			public function log(mixed $message, LogLevel $level): void {
				$this->lastMessage = $this->formatMessage($message);
				$this->lastLevel   = $level;
			}
		};
	}

	// ========================================================================
	// Helper Methods Tests, formatMessage() Text, and multiple calls.
	// ========================================================================

	public function testLogLevelsAndBasics(): void {
		$this->logger->logDebug('debug message');
		$this->assertSame('debug message', $this->logger->lastMessage);
		$this->assertSame(LogLevel::Debug, $this->logger->lastLevel);

		$this->logger->logInfo('info message');
		$this->assertSame('info message', $this->logger->lastMessage);
		$this->assertSame(LogLevel::Info, $this->logger->lastLevel);

		$this->logger->logWarning('warning message');
		$this->assertSame('warning message', $this->logger->lastMessage);
		$this->assertSame(LogLevel::Warning, $this->logger->lastLevel);

		$this->logger->logError('error message');
		$this->assertSame('error message', $this->logger->lastMessage);
		$this->assertSame(LogLevel::Error, $this->logger->lastLevel);

		$this->logger->logCritical('critical message');
		$this->assertSame('critical message', $this->logger->lastMessage);
		$this->assertSame(LogLevel::Critical, $this->logger->lastLevel);
	}

	// ========================================================================
	// formatMessage() Tests - Scalars
	// ========================================================================

	public function testFormatMessageInteger(): void {
		$this->logger->logInfo(42);
		$this->assertSame('42', $this->logger->lastMessage);
	}

	public function testFormatMessageFloat(): void {
		$this->logger->logDebug(3.14);
		$this->assertSame('3.14', $this->logger->lastMessage);
	}

	public function testFormatMessageBoolean(): void {
		$this->logger->logError(true);
		$this->assertSame('true', $this->logger->lastMessage);
	}

	public function testFormatMessageNull(): void {
		$this->logger->logWarning(null);
		$this->assertSame('NULL', $this->logger->lastMessage);
	}

	// ========================================================================
	// formatMessage() Tests - Arrays
	// ========================================================================

	public function testFormatMessageArrayEmpty(): void {
		$this->logger->logCritical([]);
		$this->assertSame('[]', $this->logger->lastMessage);
	}

	public function testFormatMessageArray(): void {
		$this->logger->logInfo(['key' => 'value', 'number' => 123, 'values' => ['a', 'b', 'c']]);
		$this->assertJson($this->logger->lastMessage);
		$this->assertSame('{"key":"value","number":123,"values":["a","b","c"]}', $this->logger->lastMessage);
	}

	public function testFormatMessageArrayWithResources(): void {
		$resource = \fopen('php://memory', 'r');
		$this->logger->logInfo(['resource' => $resource]);
		\fclose($resource);
		$this->assertSame('[JSON encoding error: Type is not supported]', $this->logger->lastMessage);
	}

	public function testFormatMessageArrayWithInvalidValues(): void {
		$data = ['name' => "Invalid\x80\x81"];
		$this->logger->logInfo($data);
		$this->assertSame('[JSON encoding error: Malformed UTF-8 characters, possibly incorrectly encoded]', $this->logger->lastMessage);
	}

	// ========================================================================
	// formatMessage() Tests - Objects
	// ========================================================================

	public function testFormatMessageStdClass(): void {
		$this->logger->logInfo((object)['name' => 'test', 'age' => 30]);
		$this->assertJson($this->logger->lastMessage);
		$this->assertSame('{"name":"test","age":30}', $this->logger->lastMessage);
	}

	public function testFormatMessageStringableObject(): void {
		$obj = new class implements \Stringable {
			public function __toString(): string {
				return 'stringable object';
			}
		};

		$this->logger->logError($obj);
		$this->assertSame('stringable object', $this->logger->lastMessage);
	}

	public function testFormatMessageObjectWithProperties(): void {
		$obj = new class {
			public string $name =    'test';
			protected int    $id     = 123;
			private float    $weight = 123.12;
		};

		$this->logger->logWarning($obj);
		$this->assertJson($this->logger->lastMessage);
		$this->assertSame('{"name":"test"}', $this->logger->lastMessage);
	}

	public function testFormatMessageJsonSerializable(): void {
		$obj = new class implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				return ['custom' => 'serialization'];
			}
		};

		$this->logger->logDebug($obj);
		$this->assertSame('{"custom":"serialization"}', $this->logger->lastMessage);
	}

	public function testFormatMessageObjectWithNoPublicProperties(): void {
		$obj = new class {
			private string $name = 'private';
		};

		$this->logger->logCritical($obj);
		$this->assertSame('[]', $this->logger->lastMessage);
	}

	// ========================================================================
	// formatMessage() Tests - Exceptions
	// ========================================================================

	public function testFormatMessageException(): void {
		$exception = new \RuntimeException('Test error');
		$this->logger->logError($exception);
		$expected = sprintf(
			'RuntimeException: Test error in %s:%d',
			$exception->getFile(),
			$exception->getLine()
		);
		$this->assertSame($expected, $this->logger->lastMessage);
	}

	// ========================================================================
	// formatMessage() Tests - Others
	// ========================================================================

	public function testFormatMessageResource(): void {
		$resource = \fopen('php://memory', 'r');
		$id = get_resource_id($resource);
		$this->logger->logCritical($resource);
		\fclose($resource);
		$this->assertSame('Resource id #' . $id, $this->logger->lastMessage);
	}
}