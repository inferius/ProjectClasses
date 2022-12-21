<?php

namespace API;

require_once("IAttributeValue.php");


class ReadOnlyAttributeValue implements IAttributeValue {
    protected $value;
    protected $name;

    public function __construct($value, $name) {
        $this->value = $value;
    }

    public function getName() {
        return $this->name;
    }

    public function getDataType() {
        return null;
    }

    public function getDataSubType() {
        return null;
    }

    public function getAttrType(): ?AttrTypeItem {
        return null;
    }

    public function isEdited() {
        return false;
    }

    public function isLocalizable() {
        return false;
    }

    public function isRequired() {
        return false;
    }

    public function isUnique() {
        return false;
    }

    public function isEmpty() {
        return $this->value == null;
    }

    public function getValue() {
        return $this->value;
    }

    public function setValue($value) {
        
    }

    public function isValid() {
        return true;
    }

    public function save() {
        if (!$this->isValid()) throw new \API\Exceptions\ValidationException("Value is not valid");
        $this->is_edited = false;

        return $this->value;
    }

    public function afterSave() {}
}