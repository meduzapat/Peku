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

namespace Peku\Tests\Unit\Helpers\Utils;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Utils\StaticUtility;

/**
 * Concrete test class for StaticUtility testing
 */
final class TestStaticUtil extends StaticUtility {
	public static function testMethod() {}
}

/**
 * Unit tests for StaticUtility base class
 */
class StaticUtilityTest extends TestCase {

	public function testConstructorIsPrivate(): void {
		$reflection  = new \ReflectionClass(TestStaticUtil::class);
		$constructor = $reflection->getConstructor();

		$this->assertNotNull($constructor);
		$this->assertTrue($constructor->isPrivate());
	}

	public function testCannotInstantiateDirectly(): void {
		$this->expectException(\Error::class);

		new TestStaticUtil();
	}
}