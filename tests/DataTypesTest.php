<?php

use PHPUnit\Framework\TestCase;
final class DataTypesTest extends TestCase {

    public static function dataProvider(): array {
        return [
            "has subtype-1" => [ \API\Model\DataTypes::ENUM, true ],
            "has subtype-2" => [ \API\Model\DataTypes::CLASSES, true ],
            "has subtype-3" => [ \API\Model\DataTypes::BOOL, false ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider')]
    public function testHasSubtype(string $dataType, bool $expected): void {
        $this->assertSame($expected, \API\Model\DataTypes::hasSubType($dataType));
    }
}