<?php

/**
 * This file is part of the Peku Framework.
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright © 2026 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Messages\Http;

use Peku\Abstractions\Retrievable;
use Peku\Validation\{Rule, RuleResult};

/**
 * Rejects requests whose Host header is not in a configured allowlist
 *
 * Fails closed: with no TrustedHosts configured, every request fails. A
 * missing allowlist is not "allow everything" - zero-trust default.
 */
final class AllowedHost implements Rule {

	/**
	 * @param TrustedHosts|null $hosts Allowlist, or null if not configured
	 */
	public function __construct(private readonly ?TrustedHosts $hosts) {}

	/**
	 * @param Retrievable $context Must contain 'headers' => Retrievable with a 'Host' key
	 */
	public function check(Retrievable $context): RuleResult {
		if ($this->hosts === null) {
			return RuleResult::fail('No trusted hosts configured');
		}

		$host = $context->get('headers')->get('Host', '');

		return $this->hosts->allows($host)
			? RuleResult::pass()
			: RuleResult::fail("Host '$host' is not in the trusted allowlist");
	}
}
