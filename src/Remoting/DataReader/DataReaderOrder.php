<?php

namespace API;

class DataReaderOrder {
    private static $orderFnc = [ "rand()" ];

    /** @var \API\Model\ClassDescription */
    private $classInfo;

    /** @var array $order */
    private $order = [];

    public function __construct($classInfo) {
        $this->classInfo = $classInfo;
    }

    public function addOrder(string $attrName, string $order = null) {
        $this->order[] = [ "attrName" => $attrName, "direction" => $order ];
    }

    public function removeOrder(string $attrName) {
        $this->clear();
        foreach ($this->order as $ord) {
            if ($ord["attrName"] == $attrName) continue;
            $this->order[] = $ord;
        }
    }

    public function setOrder(array $order) {
        $this->order = $order;
    }

    public function clear() {
        $this->setOrder([]);
    }

    public function getOrderString(): string {
        $o_q = "";
        foreach ($this->order as $ord) {
            if ($o_q != "") $o_q.= ", ";
            if (empty($ord["attrName"])) $o_attrName = $ord;
            else $o_attrName = $ord["attrName"];

            if (in_array(strtolower($o_attrName), self::$orderFnc))  {
                $o_q .= strtolower($o_attrName);
                continue;
            }

            $o_attrName = $this->classInfo->getSQLAttrName($o_attrName);

            $o_q .= $o_attrName;
            if (!empty($ord["direction"])) $o_q .= " {$ord["direction"]}";
        }

        if (!empty($o_q)) $o_q = "ORDER BY $o_q";

        return $o_q;
    }
}