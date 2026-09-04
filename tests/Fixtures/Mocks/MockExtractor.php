<?php

/**
 * This file is part of the Peku Framework.
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Tests\Fixtures\Mocks;

use Peku\Helpers\Http\Extractors\Extractor;

/**
 * Controllable Extractor test double
 *
 * Bypasses superglobal reading entirely - every source is supplied directly
 * via the constructor, so a test needs no real $_GET/$_POST/$_SERVER/$_FILES
 * state to exercise Request or HttpRequest. All arguments default to empty,
 * so `new MockExtractor()` is the no-op case.
 */
class MockExtractor extends Extractor {

	public function __construct(
		array $query  = [],
		array $data   = [],
		array $files  = [],
		array $server = []
	) {
		$this->parameters = $query;
		$this->values     = $data;
		$this->files      = $files;
		$this->server     = $server;
	}

	protected function initialize(): void {
		// Already initialized in the constructor - no superglobals touched
	}
}
