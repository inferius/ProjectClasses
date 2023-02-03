<?php

namespace API\Functions;

class Argument {

    private $name;
    private $values = [];

    public function __construct($name) {
        $this->name = strtolower($name);
    }

    public function addValue($val) {
        $this->values[] = $val;
    }

    private function beforeRead($index = 0) {
        if (empty($this->values[$index])) return null;
        $v = $this->values[$index];

        if (is_callable(\API\Configurator::$replaceTextFnc))
            $fnc = \API\Configurator::$replaceTextFnc;
            return $fnc($v);
        return $v;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    public function getValue($index = 0) {
        return $this->beforeRead($index);
    }

    public function getValues() {
        return $this->values;
    }
}