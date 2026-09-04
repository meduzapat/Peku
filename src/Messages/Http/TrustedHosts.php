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

/**
 * Allowlist of hosts this application will trust in the Host header
 *
 * Exact match only, including port - matches HttpRequest::getHost() verbatim
 * ('example.com' and 'example.com:8443' are different entries). No wildcard
 * subdomain matching in this version; add a second matcher alongside allows()
 * if that's needed later, without changing Rule/AllowedHost.
 */
final class TrustedHosts {

	/**
	 * @var array<string, true> Allowed hosts, keyed for O(1) lookup
	 */
	private array $hosts;

	private function __construct(array $hosts) {
		$this->hosts = array_fill_keys($hosts, true);
	}

	/**
	 * Build an allowlist from one or more exact host values
	 *
	 * @param string ...$hosts e.g. 'peku.dev', 'www.peku.dev'
	 */
	public static function only(string ...$hosts): self {
		return new self($hosts);
	}

	/**
	 * Whether $host is in the allowlist
	 */
	public function allows(string $host): bool {
		return isset($this->hosts[$host]);
	}
}
