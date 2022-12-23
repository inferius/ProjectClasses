<?php

namespace API;
use API\Model\DataTypes;
use API\Model\IAttributeInfo;

class AttrTypeItem {

    /** @var IAttributeInfo */
    private $attr_context;
    private $last_error;
    private $context = null;

    function __construct(IAttributeInfo $attr_info, $context = null) {
        $this->attr_context = $attr_info;
        $this->context = $context;
    }

    /**
     * @return IAttributeInfo Načtení informace atributu
     */
    public function attributeInfo() { return $this->attr_context; }

    public function getLastError() { return $this->last_error; }

    public function isValid($value): bool {
        $value = $this->runAttrMethodStatic($value, "beforeValidation",$value, $method_error);
        if ($method_error) return false;

        $validate_result = $this->runAttrMethodStatic($value, "validate", true, $method_error);
        if ($method_error) return false;
        if ($validate_result === false) {
            return false;
        }

        return true;
    }


    public function canSave($value): bool {
        if ($this->attributeInfo()->flags()->isRequired() && $this->isEmpty($value)) {
            $this->last_error = "is_required";
            return false;
        }

        if (!$this->isValid($value)) {
            $this->last_error = "not_valid";
            return false;
        }

        return true;
    }

    // public function beforeInsertValue($value) {
    //     return $this->beforeSave($value, true);
    // }

    // public function beforeUpdateValue($value) {
    //     return $this->beforeSave($value, false);
    // }

    public function beforeReadValue($value) {
        return $this->runAttrMethodStatic($value, "beforeRead",$value, $method_error);
    }

    public function beforeSave($value) {
        // if ($is_new) {
        //     $value = $this->runAttrMethodStatic($value, "beforeCreate",$value, $method_error);
        //     if ($method_error) return false;
        // }
        // else {
        //     $value = $this->runAttrMethodStatic($value, "beforeUpdate",$value, $method_error);
        //     if ($method_error) return false;
        // }

        $value = $this->runAttrMethodStatic($value, "beforeSave",$value, $method_error);
        if ($method_error) return false;

        return $value;
    }

    /**
     * Vrátí informaci o tom zda je hodnota považována za prázdnou.
     * 
     * Každý typ může být prázdný se specifickou hodnotou, takže se provede funkce u atributu is_empty_value_fnc, pokud neni provede se vychozi nastavení podle datového typu
     */
    public function isEmpty($value):bool {
        $is_error = false;
        $is_empty = $this->runAttrMethodStatic($value, "isEmpty",null, $is_error, $method_error);

        if ($this->attributeInfo()->getType() == DataTypes::CLASSES) return $value == 1;
        if ($is_empty === null) {
            return empty($value) && !($value === 0 || $value === "0");
        }
        else return boolval($is_empty);
    }

    private function runAttrMethodStatic($value, $method_name, $default_value = null, &$is_error = false, &$error_text = "") {
        if (!empty($this->attributeInfo()->methods()->{$method_name}())) {
            set_error_handler("warning_handler", E_ALL);
            try {
                return \API\UserMethod::run_code($this->attributeInfo()->methods()->{$method_name}(), [ "value" => $value, "context" => $this->context ]);
            }
            catch (\Exception $e) {
                $error_text = "{$method_name}_error";
                $is_error = true;
                return null;
            }
            finally {
                restore_error_handler();
            }
        }

        return $default_value;
    }
}