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
 * Extracts and secures all superglobals from raw sources.
 */
interface Extractable {

	/**
	 * Get query parameters (GET)
	 */
	public function getQuery(): array;

	/**
	 * Get body parameters (POST/PUT/PATCH)
	 */
	public function getData(): array;

	/**
	 * Get uploaded files
	 *
	 * @return array<string, \Peku\Helpers\Http\UploadedFile|array<\Peku\Helpers\Http\UploadedFile>>
	 */
	public function getFiles(): array;

	/**
	 * Get server variables
	 */
	public function getServer(): array;
}
