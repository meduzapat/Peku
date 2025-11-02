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
 * Interface for request data extraction
 *
 * Extractors are responsible for retrieving user-submitted data from HTTP requests.
 * They only provide bulk retrieval methods - consumers handle caching and key lookups.
 */
interface Extractable {

	/**
	 * Get all query parameters (GET)
	 *
	 * @return array Query string parameters
	 */
	public function getAll(): array;

	/**
	 * Get all body parameters (POST/PUT/PATCH)
	 *
	 * @return array Request body parameters
	 */
	public function getData(): array;

	/**
	 * Get all uploaded files
	 *
	 * @return array<string, \Peku\Helpers\Files\FileInterface> Uploaded files indexed by field name
	 */
	public function getFiles(): array;
}
