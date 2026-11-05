<?php

declare(strict_types=1);

namespace API;

class DataReaderWhere {
    private $params = [];
    private readonly \API\DataReaderWhereItem $conditions;

    public function __construct() {
        $this->conditions = new DataReaderWhereItem();
    }

    public function addCondition($cond, $param): void {
        $this->conditions->add($cond);
        $this->params[] = $param;
    }

}