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

namespace Peku\Helpers\Http;

use RuntimeException;

/**
 * HTTP upload exception
 *
 * Thrown when upload operations fail due to:
 * - Upload errors (file not uploaded, size exceeded, etc.)
 * - Invalid file state (deleted, unavailable)
 * - Deployment failures (directory creation, file move)
 */
class UploadException extends RuntimeException {}