<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author	Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link	  https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Helpers\Configs;

use Peku\Helpers\Files\FileException;

/**
 * PHP array configuration implementation
 *
 * Reads configuration from PHP files that return arrays
 *
 * WARNING: Uses include() which executes PHP code. Only use with trusted config files.
 *
 * @example <?php return ['database' => ['host' => 'localhost'], 'app' => ['debug' => true]];
 */
class Php extends Config {

	/**
	 * Read and validate PHP config file
	 * @see Config::import()
	 * @throws \Peku\Helpers\Files\FileException if the file is missing.
	 */
	protected function import(array $sourceInfo): array {
		$file = $sourceInfo['file'] ?? throw new ConfigException('file parameter required');

		if (!\file_exists($file)) {
			throw new FileException($file, "Config file not found");
		}

		$data = include $file;

		if (!\is_array($data)) {
			throw new ConfigException("Config file must return an array: $file");
		}

		return $data;
	}
}
