<?php

/**
 * This file is part of the Peku Framework.
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Tests\Unit\Messages\Http;

use PHPUnit\Framework\TestCase;
use Peku\Messages\Http\TrustedHosts;

/**
 * Unit tests for TrustedHosts
 */
class TrustedHostsTest extends TestCase {

	public function testAllowsAnExactMatch(): void {
		$hosts = TrustedHosts::only('peku.dev', 'www.peku.dev');
		$this->assertTrue($hosts->allows('peku.dev'));
		$this->assertTrue($hosts->allows('www.peku.dev'));
	}

	public function testRejectsAnythingNotListed(): void {
		$hosts = TrustedHosts::only('peku.dev');
		$this->assertFalse($hosts->allows('evil.com'));
	}

	public function testPortIsPartOfTheMatch(): void {
		$hosts = TrustedHosts::only('peku.dev');
		$this->assertFalse($hosts->allows('peku.dev:8443'));
	}

	public function testMatchIsCaseSensitive(): void {
		$hosts = TrustedHosts::only('peku.dev');
		$this->assertFalse($hosts->allows('PEKU.DEV'));
	}

	public function testEmptyAllowlistAllowsNothing(): void {
		$hosts = TrustedHosts::only();
		$this->assertFalse($hosts->allows(''));
		$this->assertFalse($hosts->allows('peku.dev'));
	}
}
