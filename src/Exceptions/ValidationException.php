<?php

namespace API\Exceptions {
	class ValidationException extends BaseException
	{
		public $custom_data;
		// Redefine the exception so message isn't optional
		/**
		 * Konstruktor pro validační chybu
		 * @param string $message Zprava vyjimky
		 * @param mixed $code Obecný kód vyjímky
		 * @param Exception $previous Předchozí exception
		 */
		public function __construct($message, $code = 0, $custom_data = null, \Exception $previous = null) {
			// some code
			$this->custom_data = $custom_data;
			// make sure everything is assigned properly
			parent::__construct($message, $code, $previous);
		}

		// custom string representation of object
		public function __toString() {
			return $this->message;
		}
	}
}
