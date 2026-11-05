<?php

declare(strict_types=1);

namespace API\Exceptions;
class AttributeValueNotFound extends BaseException
{
    // Redefine the exception so message isn't optional
    public function __construct(public $attrName,public $value,$message = "", $code = 0, ?\Exception $previous = null) {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString(): string {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }

    public function customFunction(): void {
        echo "A custom function for this type of exception\n";
    }
}
