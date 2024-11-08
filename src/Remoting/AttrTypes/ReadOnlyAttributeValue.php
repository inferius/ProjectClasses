<?php

namespace API;

class ReadOnlyAttributeValue implements IAttributeValue {
    protected $value;
    protected $name;
    protected $type;
    /**
     * @var false
     */
    protected $is_edited;

    public function __construct($value, $name) {
        $this->value = $value;
        $this->name = $name;

        if (is_int($value)) $this->type = "integer";
        else if (is_string($value)) $this->type = "string";
        else $this->type = "unknown";
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDataType(): string {
        return $this->type;
    }

    public function getDataSubType(): ?string {
        return null;
    }

    public function getAttrType(): ?AttrTypeItem {
        return null;
    }

    public function isEdited(): bool {
        return false;
    }

    public function isLocalizable(): bool {
        return false;
    }

    public function isRequired(): bool {
        return false;
    }

    public function isUnique(): bool {
        return false;
    }

    public function isEmpty(): bool {
        return $this->value == null;
    }

    public function getValue() {
        return $this->value;
    }

    public function setValue($value): void {
        
    }

    public function isValid(): bool {
        return true;
    }

    public function save() {
        if (!$this->isValid()) throw new \API\Exceptions\ValidationException("Value is not valid");
        $this->is_edited = false;

        return $this->value;
    }

    public function afterSave(): void {}

    public function delete(): void
    {
        // TODO: Implement delete() method.
    }
}
