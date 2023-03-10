<?php

namespace API;

use API\Model\IAttributeInfo;

class AttributeValue implements IAttributeValue {
    protected $value;
    protected $cache_value;

    /** @var IAttributeInfo $attr_info */
    protected $attr_info;
    /** @var \API\Event\EventManagerInterface */
    protected $_eventManager;

    /** @return \API\Event\EventManagerInterface */
    public function eventManager() {
        return $this->_eventManager;
    }
    /**
     * @var AttrTypeItem
     */
    protected $attr_type;

    protected $is_edited = false;

    public function __construct($value, IAttributeInfo $attr_info) {
        $this->value = $value;
        $this->attr_info = $attr_info;
        $this->attr_type = new AttrTypeItem($attr_info, $this);
        $this->_eventManager = new \API\Event\EventManager();
    }

    public function getName(): string {
        return $this->attr_info->getTextId();
    }

    public function getDataType(): string {
        return $this->attr_info->getType();
    }

    public function getDataSubType(): ?string {
        return $this->attr_info->getSubtype();
    }

    public function getAttrType(): AttrTypeItem {
        return $this->attr_type;
    }

    public function isEdited(): bool {
        return $this->is_edited;
    }

    public function isLocalizable(): bool {
        return $this->attr_info->flags()->isLocalizable();
    }

    public function isRequired(): bool {
        return $this->attr_info->flags()->isRequired();
    }

    public function isUnique(): bool {
        return $this->attr_info->flags()->isUnique();
    }

    public function isEmpty(): bool {
        return $this->attr_type->isEmpty($this->value);
    }

    public function getValue() {
        if (empty($this->cache_value)) {
            $this->cache_value = $this->attr_type->beforeReadValue($this->value);
        }

        return $this->cache_value;
    }

    public function setValue($value): void {
        $this->is_edited = true;
        $this->cache_value = null;
        $this->value = $value;

        $this->eventManager()->trigger("change", $this);
    }

    public function isValid(): bool {
        return $this->attr_type->isValid($this->value);
    }

    public function save() {
        if (!$this->isValid()) throw new \API\Exceptions\ValidationException("Value is not valid");
        $this->is_edited = false;

        $this->value = $this->attr_type->beforeSave($this->value);
        return $this->value;
    }

    public function afterSave(): void {
        
    }

    public function delete(): void {

    }

    public function __destruct() {
        $this->eventManager()->clearListeners("change");
    }
}