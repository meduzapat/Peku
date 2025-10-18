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

namespace Peku\Tests\Fixtures;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case with common utilities
 */
abstract class TestCase extends PHPUnitTestCase
{
	/**
	 * Get fixture file path
	 */
	protected function fixture(string $path): string {
		return PEKU_FIXTURES . '/' . $path;
	}

	/**
	 * Load fixture file contents
	 */
	protected function loadFixture(string $path): string {
		$file = $this->fixture($path);

		if (!\file_exists($file)) {
			$this->fail("Fixture not found: $path");
		}

		return \file_get_contents($file);
	}
}