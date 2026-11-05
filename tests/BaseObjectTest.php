<?php

declare(strict_types=1);

use API\BaseObject;
use API\Configurator;
use API\Exceptions\ObjectNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeConnection;

final class BaseObjectTest extends TestCase {
    protected function setUp(): void {
        Configurator::$memcache = null;
        Configurator::$editLanguageId = null;
        Configurator::$currentLanguageId = 2;
        Configurator::$connection = (new FakeConnection())
            ->on("SELECT * FROM model_classes", fn() => [ "class_name" => "Stranky", "text_id" => "pages", "table_name" => "crm_pages" ])
            ->on("SELECT mat.*", fn() => [])
            ->on("SELECT t.id as id", fn(array $p) => $p[1] === 5
                ? [ "id" => 5, "created" => new \DateTimeImmutable("2026-11-05 10:20:30"), "edited" => null ]
                : null);
    }

    protected function tearDown(): void {
        Configurator::$connection = null;
    }

    /** id z URL nebo JSON je retezec - knihovna ho prevede sama */
    public function testNumericStringIdIsConverted(): void {
        $o = new BaseObject("pages", "5");

        $this->assertSame(5, $o->getId());
        $this->assertFalse($o->isNew());
        $this->assertSame("2026-11-05 10:20:30", $o->getCreated()?->format("Y-m-d H:i:s"));
    }

    public function testEmptyIdMeansNewObject(): void {
        foreach ([ null, "", 0, "0" ] as $id) {
            $o = new BaseObject("pages", $id);
            $this->assertNull($o->getId());
            $this->assertTrue($o->isNew());
            $this->assertTrue($o->isEdited());
        }
    }

    public function testNonNumericIdIsNotFound(): void {
        $this->expectException(ObjectNotFoundException::class);
        new BaseObject("pages", "abc");
    }

    public function testLanguageFallsBackToDefault(): void {
        Configurator::$currentLanguageId = null;
        $o = new BaseObject("pages", 5);
        $this->assertSame(BaseObject::DEFAULT_LANGUAGE, $o->getLanguage());

        $o = new BaseObject("pages", 5, "3");
        $this->assertSame(3, $o->getLanguage());
    }

    public function testBindingAttribute(): void {
        $this->assertTrue(BaseObject::isBindingAttribute("url.url"));
        $this->assertFalse(BaseObject::isBindingAttribute("name"));
        $this->assertSame([ "attrName" => "url", "next" => "url" ], BaseObject::getFirstAttr("url.url"));
    }
}
