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

namespace Peku\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Peku\Controllers\Controller;

/**
 * Concrete test controllers for testing
 */
class TestController extends Controller {}

class AnotherTestController extends Controller {}

/**
 * Unit tests for Controller base class
 */
class ControllerTest extends TestCase {

	public function testExtractsClassNameFromFullyQualifiedName(): void {
		$controller = new TestController();
		$this->assertSame('TestController', $controller->getName());
	}

	public function testExtractsCorrectNameForDifferentControllers(): void {
		$controller1 = new TestController();
		$controller2 = new AnotherTestController();

		$this->assertSame('TestController',        $controller1->getName());
		$this->assertSame('AnotherTestController', $controller2->getName());
	}
}