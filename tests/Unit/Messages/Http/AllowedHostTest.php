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
use Peku\Abstractions\Collection;
use Peku\Messages\Http\{AllowedHost, TrustedHosts};

/**
 * Unit tests for AllowedHost
 */
class AllowedHostTest extends TestCase {

	private function context(string $host): Collection {
		return new Collection(['headers' => new Collection(['Host' => $host])]);
	}

	public function testFailsClosedWhenUnconfigured(): void {
		$rule   = new AllowedHost(null);
		$result = $rule->check($this->context('peku.dev'));

		$this->assertFalse($result->passed);
		$this->assertSame('No trusted hosts configured', $result->message);
	}

	public function testPassesForAnAllowedHost(): void {
		$rule   = new AllowedHost(TrustedHosts::only('peku.dev'));
		$result = $rule->check($this->context('peku.dev'));

		$this->assertTrue($result->passed);
	}

	public function testFailsForAHostNotInTheAllowlist(): void {
		$rule   = new AllowedHost(TrustedHosts::only('peku.dev'));
		$result = $rule->check($this->context('evil.com'));

		$this->assertFalse($result->passed);
		$this->assertSame("Host 'evil.com' is not in the trusted allowlist", $result->message);
	}

	public function testMissingHostHeaderIsTreatedAsEmptyString(): void {
		$context = new Collection(['headers' => new Collection()]);
		$rule    = new AllowedHost(TrustedHosts::only('peku.dev'));

		$result = $rule->check($context);

		$this->assertFalse($result->passed);
		$this->assertSame("Host '' is not in the trusted allowlist", $result->message);
	}
}
