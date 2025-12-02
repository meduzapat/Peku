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

namespace Peku\Helpers\Http\Extractors;

/**
 * Abstract base extractor with secured data storage
 *
 * Extractors secure raw request data during initialization and provide
 * access through a clean interface. No caching is performed - consumers
 * are responsible for caching calls to extractor methods.
 *
 * Future: Will include filtering and sanitization capabilities
 */
abstract class Extractor implements Extractable {


	protected array
		$parameters = [], // GET
		$values     = [], // POST/PUT/PATCH
		$server     = [], // $_SERVER
		/**
		 * Secured uploaded files
		 * @var array<string, \Peku\Helpers\Http\UploadedFile|array<\Peku\Helpers\Http\UploadedFile>>
		 */
		$files = [];

	/**
	 * Initialize extractor and secure request data
	 */
	public function __construct() {
		$this->initialize();
	}

	/**
	 * @see Extractable::getQuery()
	 */
	public function getQuery(): array {
		return $this->parameters;
	}

	/**
	 * @see Extractable::getData()
	 */
	public function getData(): array {
		return $this->values;
	}

	/**
	 * @see Extractable::getFiles()
	 */
	public function getFiles(): array {
		return $this->files;
	}

	/**
	 * @see Extractable::getServer()
	 */
	public function getServer(): array {
		return $this->server;
	}

	/**
	 * Initialize and secure request data
	 *
	 * Called once during construction. Implementations should:
	 * 1. Extract data from raw sources ($_GET, $_POST, php://input, etc.)
	 * 2. Store in secured properties ($parameters, $values, $files)
	 * 3. Optionally clear raw sources to prevent accidental access
	 */
	abstract protected function initialize(): void;
}