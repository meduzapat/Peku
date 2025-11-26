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
	 * Get query parameters (GET for HTTP, arguments for CLI)
	 *
	 * @return array Query string parameters
	 */
	public function getQuery(): array;

	/**
	 * Get all body parameters (POST/PUT/PATCH)
	 *
	 * @return array Request body parameters
	 */
	public function getData(): array;

	/**
	 * Get all uploaded files
	 *
	 * @return array<string, \Peku\Helpers\Http\UploadedFile|array<\Peku\Helpers\Http\UploadedFile>> Files
	 */
	public function getFiles(): array;
}
