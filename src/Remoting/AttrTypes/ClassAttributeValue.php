<?php

namespace API;

require_once("AttributeValue.php");

class ClassAttributeValue extends AttributeValue {
    /** @var BaseObject|null */
    protected $obj_value;
    public function __construct($value, $attr_info) {
        if ($value == null) $value = 1;
        parent::__construct($value, $attr_info);
    }

    public function getValue(): ?BaseObject {
        if (empty($this->obj_value)) {
            $this->obj_value = $this->value == 1 ? null : new BaseObject($this->subtype, $this->value);
            if (!empty($this->obj_value)) {
                $this->obj_value->setUseTransaction(false);
                $this->obj_value->eventManager()->attach("change", function() { $this->_listenerChange(); });
            }
        }

        return $this->obj_value;
    }

    public function setValue($value): void {
        $set_value = null;
        if ($value == null) {
            $set_value = 1;
        }
        else if ($value instanceof \API\BaseObject) {
            // if (!empty($this->obj_value)) {
            //     $this->obj_value->eventManager()->detach("change", $this->_listenerChange);
            // }

            $this->obj_value = $value;
            $this->value = $value->isNew() ? null : $value->getId();
            $this->obj_value->setUseTransaction(false);

            $this->obj_value->eventManager()->attach("change", function() { $this->_listenerChange(); });
        }
        else $set_value = $value;

        parent::setValue($set_value);
    }

    private function _listenerChange() {
        $this->is_edited = true;
        $this->eventManager()->trigger("change", $this);
    }

    public function save() {
        if (!$this->isValid()) throw new \API\Exceptions\ValidationException("Value is not valid");
        if (!empty($this->obj_value)) {
            $this->obj_value->save();
            $this->value = $this->obj_value->getId();
        }
        $this->is_edited = false;
        return $this->value;
    }
}