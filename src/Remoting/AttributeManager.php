<?php

namespace API;

use API\Model\DataTypes;
use API\Model\IAttributeInfo;

class AttributeManager {
    public static function get($value, IAttributeInfo $attrInfo, \API\Model\ClassDescription $classDescription, BaseObject $bo): AttributeValue {
        global $config;
        switch ($attrInfo->getType()) {
            case DataTypes::FILE:
                return new FileAttributeValue($value, $attrInfo, $classDescription->getTextId());
            case DataTypes::CLASSES:
                return new ClassAttributeValue($value, $attrInfo, $bo);
            case DataTypes::DATE:
            case DataTypes::TIME:
            case DataTypes::DATETIME:
                return new DateTimeAttributeValue($value, $attrInfo);
            case DataTypes::DECIMAL:
            case DataTypes::INT:
            case DataTypes::FLOAT:
                return new NumberAttributeValue($value, $attrInfo);
            default:
                return new AttributeValue($value, $attrInfo);
        }
    }

    
}
