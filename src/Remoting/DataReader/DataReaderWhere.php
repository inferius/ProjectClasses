<?php

namespace API;

class DataReaderWhere {
    private $params = [];
    private $conditions = null;

    public function __construct() {
        $this->conditions = new DataReaderWhereItem();
    }

    public function addCondition($cond, $param) {
        $this->conditions->add($cond);
        $this->params[] = $param;
    }

}