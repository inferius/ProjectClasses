<?php

declare(strict_types=1);
namespace API\Frontend;

/**
 * Vyjimka, ktera se zavola v php souboru sablony, zapricni, ze se sablona prestane vykreslovat, ale nespadne aplikace
 */
class StopDrawingExceptions extends \Exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 3, ?\Exception $previous = null) {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString(): string {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }

}