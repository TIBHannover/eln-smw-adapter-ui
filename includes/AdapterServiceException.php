<?php

declare( strict_types=1 );

namespace ELNSMWAdapterUI;

use RuntimeException;

/**
 * Raised when a request to the adapter service fails. Carries the i18n message key
 * (and parameters) describing the failure so the caller can show it to the user.
 */
class AdapterServiceException extends RuntimeException {

	/**
	 * @param string $messageKey i18n key of the user-facing error message
	 * @param array<int, mixed> $messageParams
	 */
	public function __construct( private string $messageKey, private array $messageParams = [] ) {
		parent::__construct( $messageKey );
	}

	public function getMessageKey(): string {
		return $this->messageKey;
	}

	/**
	 * @return array<int, mixed>
	 */
	public function getMessageParams(): array {
		return $this->messageParams;
	}
}
