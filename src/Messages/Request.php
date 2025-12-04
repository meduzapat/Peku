<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Messages;

use Peku\Helpers\Http\Extractors\Extractable;
use Peku\Abstractions\MixedCollection;

/**
 * Abstract base request providing unified interface for all request types
 *
 * Uses MixedCollection for unified data access with type casting support.
 * Implementations handle context-specific data sources (superglobals, argv, etc.)
 */
abstract class Request implements Requestable {

	/**
	 * @var RequestType Request type (GET, POST, CLI, etc.)
	 */
	protected RequestType $type;

	/**
	 * @var MixedCollection Extracted and sanitized request data with type casting
	 */
	protected MixedCollection $values;

	/**
	 * Default extractor (can be overridden globally)
	 */
	protected static ?Extractable $extractor = null;

	/**
	 * Initialize request and load data from source
	 */
	public function __construct() {
		$this->extract();
	}

	/**
	 * Extract request data from context-specific source
	 *
	 * Called during construction. Implementations load data from:
	 * - HTTP: $_GET, $_POST, $_SERVER, etc.
	 * - CLI: $argv, getopt(), etc.
	 */
	abstract protected function extract(): void;

	/**
	 * Create extractor: use custom if set, otherwise child's default
	 *
	 * @return Extractable Extractor instance
	 */
	abstract protected function createExtractor(): Extractable;

	/**
	 * @see Requestable::getType()
	 */
	public function getType(): RequestType {
		return $this->type;
	}

	/**
	 * Get request values collection
	 *
	 * @return MixedCollection Request data with type casting support
	 */
	public function values(): MixedCollection {
		return $this->values;
	}

	/**
	 * Set default extractor
	 *
	 * @param Extractable $extractor Extractor instance
	 */
	public static function setDefaultExtractor(Extractable $extractor): void {
		self::$extractor = $extractor;
	}
}
