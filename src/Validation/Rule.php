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

use Peku\Abstractions\Retrievable;

/**
 * A single, self-contained validation check
 *
 * A rule owns its own data access, evaluation, and failure reporting.
 * The context passed to check() is a Retrievable of named data sources
 * (e.g. HTTP: 'headers', 'server'), defined entirely by the caller
 * this interface makes no assumption about how many sources exist or what
 * they're called.
 */
interface Rule {

	/**
	 * Evaluate the rule against the given context
	 *
	 * @param Retrievable $context Named data sources for this request type
	 * @return RuleResult Pass, or fail with a message describing why
	 */
	public function check(Retrievable $context): RuleResult;
}
