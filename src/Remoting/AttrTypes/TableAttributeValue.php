<?php

namespace API;

class TableAttributeValue {
    protected $data_type;
    /** @var mixed */
    protected $value = null;
    public $isEdited = false;
    protected $name;

    public function __construct($attrName, $value, $data_type) {
        $this->name = $attrName;
        $this->value = $value;
        $this->data_type = $data_type;
    }

    public function setValue($value) {
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
