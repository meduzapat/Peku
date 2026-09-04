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

/**
 * Outcome of a single Rule::check() call
 *
 * Bundles the pass/fail outcome with its message so a rule builds both
 * together, in one place, from whatever data it inspected - no separate
 * accessor, no instance state held between check() and a later call.
 */
final class RuleResult {

	private function __construct(
		public readonly bool   $passed,
		public readonly string $message = ''
	) {}

	/**
	 * The rule's condition was satisfied
	 */
	public static function pass(): self {
		return new self(true);
	}

	/**
	 * The rule's condition failed
	 *
	 * @param string $message Reason, specific to what was checked
	 */
	public static function fail(string $message): self {
		return new self(false, $message);
	}
}
