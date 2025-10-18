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

namespace Peku\Tests\Unit\Helpers\Files;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Files\FileException;

/**
 * Unit tests for FileException
 */
class FileExceptionTest extends TestCase {

	public function testConstructorWithFilePathOnly(): void {
		$exception = new FileException('/path/to/file.txt');

		$this->assertSame('/path/to/file.txt', $exception->getFilePath());
		$this->assertSame('File operation failed: /path/to/file.txt', $exception->getMessage());
	}

	public function testConstructorWithReason(): void {
		$exception = new FileException('/path/to/file.txt', 'File not found');

		$this->assertSame('/path/to/file.txt', $exception->getFilePath());
		$this->assertSame('File not found: /path/to/file.txt', $exception->getMessage());
	}
}