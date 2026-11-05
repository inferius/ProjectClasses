<?php

declare(strict_types=1);

namespace API;

class DataReaderOrder {
    private static array $orderFnc = [ "rand()" ];

    /** @var array $order */
    private $order = [];

    /**
     * @param \API\Model\ClassDescription $classInfo
     */
    public function __construct(private $classInfo)
    {
    }

    public function addOrder(string $attrName, ?string $order = null): void {
        $this->order[] = [ "attrName" => $attrName, "direction" => $order ];
    }

    public function removeOrder(string $attrName): void {
        $this->clear();
        foreach ($this->order as $ord) {
            if ($ord["attrName"] == $attrName) continue;
            $this->order[] = $ord;
        }
    }

    public function setOrder(array $order): void {
        $this->order = $order;
    }

    public function clear(): void {
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