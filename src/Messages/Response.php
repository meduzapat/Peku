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

namespace Peku\Messages;

/**
 * Abstract base response providing unified interface for all response types
 *
 * Provides common functionality for status codes and content management.
 * Implementations handle context-specific output (HTTP headers, CLI stdout, etc.)
 * and content formatting (JSON, XML, etc.)
 */
abstract class Response implements Responseable {

	/**
	 * @var mixed Response content (string, array, object, etc.)
	 */
	protected mixed $content = '';

	/**
	 * @var int Response result code
	 */
	protected int $code = 0;

	/**
	 * Initialize response with optional content and code
	 *
	 * @param mixed $content Optional initial content
	 * @param int   $code    Optional result code (default: 0)
	 */
	public function __construct(mixed $content = '', int $code = 0) {
		$this
			->setContent($content)
			->setCode($code);
	}

	/**
	 * @see Responseable::setContent()
	 */
	final public function setContent(mixed $content): static {
		$this->validate($content);
		$this->content = $content;
		return $this;
	}

	/**
	 * @see Responseable::getContent()
	 */
	public function getContent(): mixed {
		return $this->content;
	}

	/**
	 * @see Responseable::setCode()
	 */
	public function setCode(int $code): static {
		$this->code = $code;
		return $this;
	}

	/**
	 * @see Responseable::getCode()
	 */
	public function getCode(): int {
		return $this->code;
	}

	/**
	 * Send response to client
	 */
	final public function send(): void {
		echo $this->processContent();
	}

	/**
	 * Checks if the content is valid.
	 * @param mixed $content
	 * @throws \InvalidArgumentException if the check fails.
	 */
	abstract protected function validate(mixed $content): void;

	/**
	 * Convert the contents into string.
	 *
	 * @return string
	 */
	abstract protected function processContent(): string;
}
