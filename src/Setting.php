<?php

namespace API\Frontend;

require_once ("Configurator.php");
require_once ("SettingItem.php");

final class Setting {
    private $values = [];

    private static $attr_map = [ "int_val", "decimal_val", "short_text", "long_text", "datetime_val", "formatted_text", "boolean_val"];
    private $_context = null;

    function __construct() {

        $this->_context = \API\Configurator::$explorer->table("frontend_setting");
        
        foreach ($this->_context as $si) {
            $t = self::$attr_map[$si->type];
            $this->values[$si->text_id]  = new SettingItem($t, $si[$t], $si->text_id, $si->name, $si->type);
        }

    }

    /**
     * Vrátí hodnotu atributu
     *
     * @param string $attrname Název atributu
     * @return mixed|null Hodnota atributu
     */
    public function getValue(string $attrname) {
        if (empty($this->values[$attrname])) return null;

        return $this->getItem($attrname)->getValue();
    }

    //public function getItem($attrname): ?SettingItem {

    /**
     * @param string $attrname Název atributu
     * @return SettingItem|null
     */
    public function getItem(string $attrname) {
        if (empty($this->values[$attrname])) return null;

        return $this->values[$attrname];
    }

    /**
     * @param string $attrname Název atributu
     * @param mixed $value Nová hodnota atributu
     * @return bool
     */
    public function setValue(string $attrname, $value): bool {
        if (empty($this->values[$attrname])) return false;

        $this->values[$attrname]->setValue($value);

        return true;
    }

    /**
     * Vrátí všechny položky nastavení
     * @return SettingItem[]
     */
    public function getAllItems(): array
    {
        return array_values($this->values);
    }
}