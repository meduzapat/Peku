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
use Peku\Validation\RuleResult;

/**
 * Unit tests for RuleResult
 */
class RuleResultTest extends TestCase {

	public function testPassHasNoMessage(): void {
		$result = RuleResult::pass();
		$this->assertTrue($result->passed);
		$this->assertSame('', $result->message);
	}

	public function testFailCarriesItsMessage(): void {
		$result = RuleResult::fail('not allowed');
		$this->assertFalse($result->passed);
		$this->assertSame('not allowed', $result->message);
	}
}
