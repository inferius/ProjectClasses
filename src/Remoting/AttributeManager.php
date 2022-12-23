<?php

namespace API;

use API\Model\DataTypes;
use API\Model\IAttributeInfo;

class AttributeManager {
    public static function get($value, IAttributeInfo $attrInfo, \API\Model\ClassDescription $classDescription): AttributeValue {
        global $config;
        switch ($attrInfo->getType()) {
            case DataTypes::FILE:
                return new FileAttributeValue($value, $attrInfo, $classDescription->getTextId());
            case DataTypes::CLASSES:
                return new ClassAttributeValue($value, $attrInfo);
            default:
                return new AttributeValue($value, $attrInfo);
        }
    }

    
}
