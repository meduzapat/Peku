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
use Peku\Helpers\Loggers\Noop;
use Peku\Helpers\Loggers\LogLevel;

/**
 * Unit tests for Noop logger implementation
 */
class NoopTest extends TestCase {

	public function testLogDoesNothing(): void {
		$this->expectNotToPerformAssertions();
		$logger = new Noop();
		$logger->logDebug('debug');
		$logger->logInfo('info');
		$logger->logWarning('warning');
		$logger->logError('error');
		$logger->logCritical('critical');
	}
}
