<?php

declare(strict_types=1);

namespace API\Functions;

class Argument {

    private readonly string $name;
    private $values = [];

    public function __construct($name) {
        $this->name = strtolower($name);
    }

    public function addValue($val): void {
        $this->values[] = $val;
    }

    private function beforeRead($index = 0) {
        if (empty($this->values[$index])) return null;
        $v = $this->values[$index];

        // drive chybely zavorky: bez nastavene funkce volalo nedefinovanou $fnc
        if (is_callable(\API\Configurator::$replaceTextFnc)) {
            $fnc = \API\Configurator::$replaceTextFnc;
            return $fnc($v);
        }
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