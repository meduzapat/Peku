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

	/**
	 * Secured query parameters (GET)
	 * @var array
	 */
	protected array $parameters = [];

	/**
	 * Secured body parameters (POST/PUT/PATCH)
	 * @var array
	 */
	protected array $values = [];

	/**
	 * Secured uploaded files
	 * @var array<string, FileInterface>
	 */
	protected array $files = [];

	/**
	 * Initialize extractor and secure request data
	 */
	public function __construct() {
		$this->initialize();
	}

	/**
	 * @see Extractable::getAll()
	 */
	public function getAll(): array {
		return $this->securedGet;
	}

	/**
	 * @see Extractable::getData()
	 */
	public function getData(): array {
		return $this->securedPost;
	}

	/**
	 * @see Extractable::getFiles()
	 */
	public function getFiles(): array {
		return $this->securedFiles;
	}

	/**
	 * Initialize and secure request data
	 *
	 * Called once during construction. Implementations should:
	 * 1. Extract data from raw sources ($_GET, $_POST, php://input, etc.)
	 * 2. Store in secured properties ($securedGet, $securedPost, $securedFiles)
	 * 3. Optionally clear raw sources to prevent accidental access
	 */
	abstract protected function initialize(): void;
}
