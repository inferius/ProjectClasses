<?php

namespace API;

const WHERE_SIGN_AND = "AND";
const WHERE_SIGN_OR = "OR";

class DataReaderWhereItem {
    private $sign = "AND";
    private $items = [];

    /**
     * @param string | DataReaderWhereItem | null $cond
     * @param string $sign
     */
    public function __construct($cond = null, string $sign = WHERE_SIGN_OR) {
        $this->sign = $sign;

        $this->add($cond);
    }

    /**
     * @param string | DataReaderWhereItem $item
     * @return void
     */
    public function add($item) {
        if (empty($item)) return;
        if (is_string($item) || $item instanceof DataReaderWhereItem) $this->items[] = $item;
    }

    public function changeSign(string $sign) {
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