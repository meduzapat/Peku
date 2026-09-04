<?php

/**
 * This file is part of the Peku Framework.
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Peku\Abstractions\{Collection, MutableCollection};
use Peku\Validation\{Rule, RuleResult, Rules, UntrustedRequestException};

/**
 * Unit tests for Rules::enforce()
 */
class RulesTest extends TestCase {

	private function rule(bool $passes, string $message = 'failed'): Rule {
		return new class ($passes, $message) implements Rule {
			public function __construct(private bool $passes, private string $message) {}
			public function check(\Peku\Abstractions\Retrievable $context): RuleResult {
				return $this->passes ? RuleResult::pass() : RuleResult::fail($this->message);
			}
		};
	}

	public function testEnforcePassesSilentlyWhenAllRulesPass(): void {
		$rules = (new MutableCollection())
			->set('a', $this->rule(true))
			->set('b', $this->rule(true));

		$this->expectNotToPerformAssertions();
		Rules::enforce($rules, new Collection());
	}

	public function testEnforceThrowsOnFirstFailure(): void {
		$rules = (new MutableCollection())
			->set('a', $this->rule(true))
			->set('b', $this->rule(false, 'b failed'));

		$this->expectException(UntrustedRequestException::class);
		$this->expectExceptionMessage('b failed');
		Rules::enforce($rules, new Collection());
	}

	public function testEnforcePassesOverEmptyRuleSet(): void {
		$this->expectNotToPerformAssertions();
		Rules::enforce(new MutableCollection(), new Collection());
	}
}