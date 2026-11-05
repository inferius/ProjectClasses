<?php

declare(strict_types=1);

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
    private static array $constants = [];
    private static array $const2val = [];
    private static array $val2const = [];

    private static bool $isInitialized = false;
    private static function _init(): void {
        if (self::$isInitialized) return;
        self::$isInitialized = true;

        $thisClass = new \ReflectionClass(\API\Model\DataTypes::class);
        self::$const2val = $thisClass->getConstants();
        self::$val2const = array_flip(self::$const2val);
        self::$constants = array_values(self::$const2val);
    }

    public static function hasSubtype(string $dataType): bool {
        self::_init();

        if (!in_array($dataType, self::$constants)) {
            throw new \InvalidArgumentException("DataType '$dataType' is not valid.");
        }

        return match ($dataType) {
            self::CLASSES, self::ENUM => true,
            default => false,
        };
    }

}

