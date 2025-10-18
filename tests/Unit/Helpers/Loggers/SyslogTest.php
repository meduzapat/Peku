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

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Peku\Helpers\Loggers\Syslog;
use Peku\Helpers\Loggers\LogLevel;

/**
 * Unit tests for Syslog logger implementation
 */
class SyslogTest extends TestCase {

	use PHPMock;

	private Syslog $logger;

	protected function setUp(): void {
		$this->logger = new Syslog('TestApp', LOG_PID, LOG_USER);
	}

	// ========================================================================
	// Syslog Function Calls
	// ========================================================================

	public function testLogCallsSyslogFunctions(): void {
		$openlog = $this->getFunctionMock('Peku\\Helpers\\Loggers', 'openlog');
		$openlog->expects($this->once())->with('TestApp', LOG_PID, LOG_USER);

		$syslog = $this->getFunctionMock('Peku\\Helpers\\Loggers', 'syslog');
		$syslog->expects($this->once())->with(LOG_INFO, 'Test message');

		$closelog = $this->getFunctionMock('Peku\\Helpers\\Loggers', 'closelog');
		$closelog->expects($this->once());

		$this->logger->logInfo('Test message');
	}

	// ========================================================================
	// Log Level Mapping
	// ========================================================================

	/**
	 * @dataProvider logLevelProvider
	 */
	public function testLogLevelMapping(LogLevel $logLevel, int $expectedPriority): void {
		$this->getFunctionMock('Peku\\Helpers\\Loggers', 'openlog');
		$this->getFunctionMock('Peku\\Helpers\\Loggers', 'closelog');

		$syslog = $this->getFunctionMock('Peku\\Helpers\\Loggers', 'syslog');
		$syslog->expects($this->once())->with($expectedPriority, $this->anything());

		$this->logger->log('test', $logLevel);
	}

	public static function logLevelProvider(): array {
		return [
			'debug'    => [LogLevel::Debug,    LOG_DEBUG],
			'info'     => [LogLevel::Info,     LOG_INFO],
			'warning'  => [LogLevel::Warning,  LOG_WARNING],
			'error'    => [LogLevel::Error,    LOG_ERR],
			'critical' => [LogLevel::Critical, LOG_CRIT],
		];
	}
}