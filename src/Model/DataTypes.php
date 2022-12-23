<?php

namespace API\Model;

class DataTypes {
    const STRINGS = "string";
    const TEXT = "text";
    const BOOL = "bool";
    const INT = "int";
    const FLOAT = "float";
    const DECIMAL = "decimal";
    const DATE = "date";
    const TIME = "time";
    const DATETIME = "datetime";
    const FILE = "file";
    const ENUM = "enum";
    const CLASSES = "class";

    /** @var string[] $constants */
    private static $constants = [];
    private static $const2val = [];
    private static $val2const = [];

    private static $isInitialized = false;
    private static function _init() {
        if (self::$isInitialized) return;
        self::$isInitialized = true;

        $thisClass = new \ReflectionClass('API\Model\DataTypes');
        self::$const2val = $thisClass->getConstants();
        self::$val2const = array_flip(self::$const2val);
        self::$constants = array_values(self::$const2val);
    }

    public static function hasSubtype(string $dataType): bool {
        self::_init();

        if (!in_array($dataType, self::$constants)) {
            throw new \InvalidArgumentException("DataType '$dataType' is not valid.");
        }

        switch ($dataType) {
            case self::CLASSES:
            case self::ENUM:
                return true;
            default: return false;
        }
    }

}

