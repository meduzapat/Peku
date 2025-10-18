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

namespace Peku\Tests\Unit\Helpers\Configs;

use Peku\Helpers\Configs\Noop;
use Peku\Tests\Fixtures\TestCase;

/**
 * Unit tests for Noop config implementation
 *
 * Tests only the behavior specific to Noop class.
 * Base Config functionality is tested in ConfigTest.php
 */
class NoopTest extends TestCase {

	/**
	 * Test that the Noop config always returns an empty array
	 * regardless of constructor parameters
	 */
	public function testAlwaysReturnsEmptyArray(): void
	{
		// Test with empty parameters
		$config1 = new Noop([]);
		$this->assertSame([], $config1->getAll());

		// Test with arbitrary parameters
		$config2 = new Noop(['some' => 'parameter']);
		$this->assertSame([], $config2->getAll());
	}

	/**
	 * Test that the Noop config accepts any parameters without errors
	 * (parameters that might cause other implementations to throw exceptions)
	 */
	public function testAcceptsAnyParametersWithoutError(): void
	{
		// Parameters that would typically cause errors in other implementations
		$config = new Noop(['file' => null]);
		$this->assertSame([], $config->getAll());
	}
}
