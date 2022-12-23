<?php

use PHPUnit\Framework\TestCase;
final class DataTypesTest extends TestCase {

    public function dataProvider(): array {
        return [
            "has subtype-1" => [ \API\Model\DataTypes::ENUM, true ],
            "has subtype-2" => [ \API\Model\DataTypes::CLASSES, true ],
            "has subtype-3" => [ \API\Model\DataTypes::BOOL, false ],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testHasSubtype(string $dataType, bool $expected) {
        $this->assertSame($expected, \API\Model\DataTypes::hasSubType($dataType));
    }
}