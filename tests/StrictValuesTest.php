<?php

declare(strict_types=1);

use API\Configurator;
use API\Frontend\SettingItem;
use API\Utils\Value;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeConnection;

/**
 * Hodnoty z DB, ktere PHP bez strict_types prevadelo samo (code review 2.0): DECIMAL jako retezec, Nette DateTime.
 */
final class StrictValuesTest extends TestCase {
    protected function tearDown(): void {
        Configurator::$locale = Configurator::DEFAULT_LOCALE;
        Configurator::$connection = null;
        Configurator::$memcache = null;
    }

    public function testValueConversions(): void {
        $this->assertSame(199.5, Value::toNumber("199.50"));
        $this->assertSame(12, Value::toNumber(" 12 "));
        $this->assertSame(0, Value::toNumber(null));
        $this->assertSame(0, Value::toNumber("abc"));

        $dt = new \DateTimeImmutable("2026-11-05 10:00:00");
        $this->assertSame($dt->getTimestamp(), Value::toTimestamp($dt));
        $this->assertSame(strtotime("2026-11-05"), Value::toTimestamp("2026-11-05"));
        $this->assertNull(Value::toTimestamp(null));
        $this->assertNull(Value::toTimestamp("neni datum"));
    }

    public function testDecimalSettingFromDbString(): void {
        $item = new SettingItem("decimal_val", "1234.50", "price", "Cena", 1);
        $this->assertSame("1 235", $item->toFormat());
        $this->assertSame("1 234,50", $item->toFormat(null, 2));
    }

    public function testDatetimeSettingFromNetteDateTime(): void {
        $item = new SettingItem("datetime_val", new \DateTimeImmutable("2026-11-05 10:20:30"), "since", "Od", 4);
        $this->assertSame("5. 11. 2026", $item->toFormat("j. n. Y"));

        $empty = new SettingItem("datetime_val", null, "since", "Od", 4);
        $this->assertSame("", $empty->toFormat("j. n. Y"));
        $this->assertNull($empty->toFormat("timestamp"));
    }

    public function testNullLocaleFallsBackToDefault(): void {
        // weby prirazuji locale ze session, kde muze chybet
        Configurator::$locale = null;
        $this->assertSame("cs_CZ", Configurator::locale());
    }

    public function testUnknownClassHasClearError(): void {
        Configurator::$connection = (new FakeConnection())
            ->on("SELECT * FROM model_classes", fn() => null)
            ->on("SELECT mat.*", fn() => []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Class 'neexistuje' not found");
        \API\Model\ClassDescription::get("neexistuje");
    }

    public function testPlainUrlWithoutArgumentUsesRequestUri(): void {
        $saved = $_SERVER["REQUEST_URI"] ?? null;
        $_SERVER["REQUEST_URI"] = "/novinky/clanek/?a=1";
        try {
            $pu = new \API\PlainUrl();
            $this->assertSame("clanek", $pu->last()?->token());
            $this->assertSame("a=1", $pu->query());
        }
        finally {
            if ($saved === null) unset($_SERVER["REQUEST_URI"]); else $_SERVER["REQUEST_URI"] = $saved;
        }
    }

    public function testUploadExceptionWithStringCode(): void {
        $e = new \API\Exceptions\UploadException("1");
        $this->assertSame(UPLOAD_ERR_INI_SIZE, $e->getCode());
        $this->assertStringContainsString("upload_max_filesize", $e->getMessage());
    }
}
