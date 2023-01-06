<?php

namespace API\Exceptions;
/**
 * Vyjimka, ktera se zavola v php souboru sablony, zapricni, ze zobrazi 404
 */
class PageNotFoundException extends BaseException {
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 3, \Exception $previous = null) {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}