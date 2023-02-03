<?php

namespace API;

use API\Exceptions\AttributeTypeNotFound;

class DataReaderConfigurator {
    private static $orderFnc = [ "rand()" ];

    /** @var \API\Model\ClassDescription */
    private $classInfo;
    private $langId;
    private $condition;
    private $filterCondition;

    private $filterParams;

    private $order;

    private $page;

    private $limit;

    public function getClassInfo(): \API\Model\ClassDescription {
        return $this->classInfo;
    }
    public function setClassInfo($classInfo) {
        $this->classInfo = $classInfo;
    }

    public function getLangId(): int {
        return $this->langId;
    }
    public function setLangId($l_id) {
        $this->langId = $l_id;
    }

    public function getCondition(): string {
        return $this->condition;
    }

    public function getFilterCondition(): string {
        return $this->filterCondition;
    }

    public function getOrder(): array {
        return $this->order;
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

            $o_attrName = $this->getFullAttr($o_attrName);

            $o_q .= $o_attrName;
            if (!empty($ord["direction"])) $o_q .= " {$ord["direction"]}";
        }

        return $o_q;
    }

    /**
     * @throws AttributeTypeNotFound
     */
    public function getFullAttr(string $attrName): string {
        return self::getFullAttrStatic($attrName, $this->getClassInfo());
    }

    /**
     * @throws AttributeTypeNotFound
     */
    public static function getFullAttrStatic(string $attrName, \API\Model\ClassDescription $classInfo) {
        if (strpos($attrName, ".") !== false) {
            $attrInfo = $classInfo->getAttributeInfo($attrName);
            $info = $attrInfo->getSpecification();
            if ($info instanceof \API\Model\ClassDescription) {
                $t = $attrInfo->flags()->isLocalizable() ? $info->table()->getTableLangName() : $info->table()->getTableName();
                return "$t.{$attrInfo->getAlias()}";
            }
            else throw new \API\Exceptions\AttributeTypeNotFound("Attribute '{$attrName}' not found");
        }
        else return "{$classInfo->table()->getTableName()}.$attrName";
    }
}