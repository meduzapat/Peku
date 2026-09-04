<?php

/**
 * This file is part of the Peku Framework.
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Validation;

use Peku\Abstractions\{MutableCollection, Retrievable};
use Peku\Helpers\Utils\StaticUtility;

/**
 * Runs a set of rules against a context, stopping at the first failure
 */
final class Rules extends StaticUtility {

	/**
	 * Evaluate every rule in $rules against $context
	 *
	 * @param MutableCollection $rules   Rule instances, keyed by name
	 * @param Retrievable       $context Named data sources
	 * @throws UntrustedRequestException On the first failing rule
	 */
	public static function enforce(MutableCollection $rules, Retrievable $context): void {
		foreach ($rules as $rule) {
			$result = $rule->check($context);
			if (!$result->passed) {
				throw new UntrustedRequestException($result->message);
			}
		}
	}
}
