<?php

declare(strict_types=1);

namespace API;

class TableAttributeValue {
    public $isEdited = false;

    /**
     * @param mixed $value
     */
    public function __construct(protected $name, protected $value, protected $data_type)
    {
    }

    public function setValue($value): void {
        $this->isEdited = true;
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function save() {
        $this->isEdited = false;
        return $this->value;
    }
}
