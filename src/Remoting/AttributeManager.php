<?php

declare(strict_types=1);

namespace API;

use API\Model\DataTypes;
use API\Model\IAttributeInfo;

class AttributeManager {
    public static function get($value, IAttributeInfo $attrInfo, \API\Model\ClassDescription $classDescription, BaseObject $bo): AttributeValue {
        global $config;
        return match ($attrInfo->getType()) {
            DataTypes::FILE => new FileAttributeValue($value, $attrInfo, $classDescription->getTextId()),
            DataTypes::CLASSES => new ClassAttributeValue($value, $attrInfo, $bo),
            DataTypes::DATE, DataTypes::TIME, DataTypes::DATETIME => new DateTimeAttributeValue($value, $attrInfo),
            DataTypes::DECIMAL, DataTypes::INT, DataTypes::FLOAT => new NumberAttributeValue($value, $attrInfo),
            default => new AttributeValue($value, $attrInfo),
        };
    }

    
}
