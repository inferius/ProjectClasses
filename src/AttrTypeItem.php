<?php

namespace API;
use API\Model\DataTypes;
use API\Model\IAttributeInfo;

class AttrTypeItem {

    /** @var \Nette\Database\IRow */
    private $attr_context;
    private $last_error;
    private $context = null;

    function __construct($row_data, $context = null) {
        $this->attr_context = $row_data;
        $this->context = $context;
    }

    public function isRequired(): bool { return boolval($this->attr_context->is_required); }
    public function isUnique(): bool { return boolval($this->attr_context->is_unique); }

    public function getName() { return $this->attr_context->name; }
    public function getDescription() { return $this->attr_context->name; }
    public function getLastError() { return $this->last_error; }

    public function isValid($value) {
        $value = $this->runAttrMethodStatic($value, "before_validation_fnc",$value, $method_error);
        if ($method_error) return false;

        $validate_result = $this->runAttrMethodStatic($value, "validate_fnc", true, $method_error);
        if ($method_error) return false;
        if ($validate_result === false) {
            return false;
        }

        return true;
    }


    public function canSave($value): bool {
        if ($this->attr_context->is_required == 1 && $this->isEmpty($value)) {
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
        return $this->runAttrMethodStatic($value, "before_read_fnc",$value, $method_error);
    }

    public function beforeSave($value) {
        // if ($is_new) {
        //     $value = $this->runAttrMethodStatic($value, "before_create_fnc",$value, $method_error);
        //     if ($method_error) return false;
        // }
        // else {
        //     $value = $this->runAttrMethodStatic($value, "before_update_fnc",$value, $method_error);
        //     if ($method_error) return false;
        // }

        $value = $this->runAttrMethodStatic($value, "before_save_fnc",$value, $method_error);
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
        $is_empty = $this->runAttrMethodStatic($value, "is_empty_value_fnc",null, $is_error, $method_error);
        if (method_exists($this->attr_context, "getType") && $this->attr_context->getType() == "class") return $value == 1;

        if ($is_empty === null) {
            return empty($value) && !($value === 0 || $value === "0");
        }
        else return boolval($is_empty);
    }

    private function runAttrMethodStatic($value, $method_name, $default_value = null, &$is_error = false, &$error_text = "") {
        if (!empty($this->attr_context->$method_name)) {
            //set_error_handler("warning_handler", E_ALL);
            try {
                return \API\UserMethod::run_code($this->attr_context->$method_name, [ "value" => $value, "context" => $this->context ]);
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