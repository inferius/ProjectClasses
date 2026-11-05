<?php

declare(strict_types=1);

namespace API\Exceptions {
	class UserNotFoundException extends BaseException
	{
		// Redefine the exception so message isn't optional
		public function __construct($message, $code = 1, ?\Exception $previous = null) {
			// some code

			// make sure everything is assigned properly
			parent::__construct($message, $code, $previous);
		}

		// custom string representation of object
		public function __toString(): string {
			return self::class . ": [{$this->code}]: {$this->message}\n";
		}

	}
}
