<?php

namespace API;

class DataReaderWhereItem {
    private $sign = "AND";
    private $items = [];

    public function __construct($sign = "AND") {
        $this->sign = $sign;
    }

    public function add($item) {
        $this->items[] = $item;
    }

    public function changeSign($sign) {
        $this->sign = $sign;
    }

    public function getConditionString(): string {
        $cond = "";
        $first = true;
        foreach ($this->items as $item) {
            if (!$first) {
                $cond .= " {$this->sign} ";
            }
            $first = false;
            if (is_string($item)) {
                $cond .= $item;
            }
            else if ($item instanceof DataReaderWhereItem) {
                $cond .= "(" . ( $item->getConditionString() ) . ")";
            }
        }

        return $cond;
    }
}