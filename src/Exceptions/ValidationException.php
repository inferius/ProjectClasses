<?php

declare(strict_types=1);

namespace API\Exceptions {
	class ValidationException extends BaseException
	{
		// Redefine the exception so message isn't optional
		/**
		 * Konstruktor pro validační chybu
		 * @param string $message Zprava vyjimky
		 * @param mixed $code Obecný kód vyjímky
		 * @param \Exception|null $previous Předchozí exception
		 */
		public function __construct($message, $code = 0, public $custom_data = null, ?\Exception $previous = null) {
			// make sure everything is assigned properly
			parent::__construct($message, $code, $previous);
		}

		// custom string representation of object
		public function __toString(): string {
			return $this->message;
		}
	}
}
