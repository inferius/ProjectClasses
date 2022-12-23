<?php

namespace API\Model;

class MethodsAttributeInfo {
    private $before_validation_fnc;
    private $validate_fnc;
    private $is_empty_value_fnc;
    private $before_save_fnc;
    private $after_save_fnc;
    private $before_create_fnc;
    private $after_create_fnc;
    private $before_update_fnc;
    private $after_update_fnc;
    private $before_read_fnc;


    /** Definice funkce volaná před validací */
    public function beforeValidation(): ?string { return $this->before_validation_fnc; }
    /** Definice funkce volana pro validaci */
    public function validate(): ?string { return $this->validate_fnc; }
    /** Definice funkce volající pro kontrolu prázdné hodnoty */
    public function isEmpty(): ?string { return $this->is_empty_value_fnc; }
    /** Definice funkce volaná před každým uložením */
    public function beforeSave(): ?string { return $this->before_save_fnc; }
    /** Definice funkce volaná po každém uložení */
    public function afterSave(): ?string { return $this->after_save_fnc; }
    /** Definice funkce volaná před uložením nového objektu */
    public function beforeCreate(): ?string { return $this->before_create_fnc; }
    /** Definice funkce volaná po uložení nového objektu  */
    public function afterCreate(): ?string { return $this->after_create_fnc; }
    /** Definice funkce volaná před uložení editace */
    public function beforeUpdate(): ?string { return $this->before_update_fnc; }
    /** Definice funkce volaná po uložení eeditace */
    public function afterUpdate(): ?string { return $this->after_update_fnc; }
    /** Definice funkce volaná před čtením hodnoty atributu */
    public function beforeRead(): ?string { return $this->before_read_fnc; }


    public function __construct($row_data) {
        $this->before_validation_fnc = $row_data["before_validation_fnc"];
        $this->validate_fnc = $row_data["validate_fnc"];
        $this->is_empty_value_fnc = $row_data["is_empty_value_fnc"];
        $this->before_save_fnc = $row_data["before_save_fnc"];
        $this->after_save_fnc = $row_data["after_save_fnc"];
        $this->before_create_fnc = $row_data["before_create_fnc"];
        $this->after_create_fnc = $row_data["after_create_fnc"];
        $this->before_update_fnc = $row_data["before_update_fnc"];
        $this->after_update_fnc = $row_data["after_update_fnc"];
        $this->before_read_fnc = $row_data["before_read_fnc"];
    }
}