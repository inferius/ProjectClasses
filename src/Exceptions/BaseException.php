<?php

declare(strict_types=1);

namespace API\Exceptions {
    class BaseException extends \Exception
    {
        // Redefine the exception so message isn't optional
        /**
         * Konstruktor pro validační chybu
         * @param string $message Lokalizační klíč statické lokalizační databáze
         * @param mixed $code Obecný kód vyjímky
         * @param \Exception $previous Předchozí exception
         */
        public function __construct($message, $code = 0, ?\Exception $previous = null) {
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
