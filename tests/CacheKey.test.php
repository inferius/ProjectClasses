<?php

use API\Configurator;
use API\Model\ClassDescription;
use API\Model\EnumDescription;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeMemcache;

final class CacheKeyTest extends TestCase {
    /** @var FakeMemcache */
    private $memcache;

    protected function setUp(): void {
        $this->memcache = new FakeMemcache();
        Configurator::$memcache = $this->memcache;
        Configurator::$cachePrefix = "";
    }

    protected function tearDown(): void {
        Configurator::$memcache = null;
        Configurator::$cachePrefix = "";
    }

    public function testKeyWithoutPrefixStaysAsBefore(): void {
        $this->assertSame("ModelClassDescription[MODEL]:pages", Configurator::cacheKey("ModelClassDescription[MODEL]:pages"));
    }

    public function testKeyWithProjectPrefix(): void {
        Configurator::$cachePrefix = "atoda";
        $this->assertSame("atoda:ModelClassDescription[MODEL]:pages", Configurator::cacheKey("ModelClassDescription[MODEL]:pages"));
    }

    public function testForgetDeletesNewAndLegacyClassKeys(): void {
        Configurator::$cachePrefix = "atoda";
        ClassDescription::forget("pages");

        $this->assertSame([ "atoda:ModelClassDescription[MODEL]:pages", "atoda:ModelClassDescription:pages", "ModelClassDescription:pages" ], $this->memcache->deleted);
    }

    public function testForgetWithoutPrefixDeletesEachKeyOnce(): void {
        ClassDescription::forget("pages");
        $this->assertSame([ "ModelClassDescription[MODEL]:pages", "ModelClassDescription:pages" ], $this->memcache->deleted);
    }

    public function testLegacyDescriptionUsesPrefixedKey(): void {
        Configurator::$cachePrefix = "atoda";
        Configurator::$connection = (new \Tests\Support\FakeConnection())
            ->on("SELECT * FROM model_classes", function () { return [ "class_name" => "Stranky", "text_id" => "pages", "table_name" => "crm_pages" ]; })
            ->on("SELECT mat.*", function () { return []; });

        $data = \API\ClassDescription::get("pages");

        $this->assertSame("crm_pages", $data["table"]);
        $this->assertSame([ "atoda:ModelClassDescription:pages" ], array_keys($this->memcache->data));
        Configurator::$connection = null;
    }

    public function testEnumCacheMissDoesNotWarn(): void {
        Configurator::$connection = (new \Tests\Support\FakeConnection())
            ->on("SELECT * FROM model_enums", function () { return [ "id" => 1 ]; })
            ->on("SELECT text_id, lockey", function () { return []; });

        $warnings = 0;
        set_error_handler(function ($severity) use (&$warnings) {
            if ($severity === E_USER_WARNING) $warnings++;
            return true;
        });
        try {
            $this->assertInstanceOf(EnumDescription::class, EnumDescription::get("color"));
        }
        finally {
            restore_error_handler();
            Configurator::$connection = null;
        }

        $this->assertSame(0, $warnings);
        $this->assertArrayHasKey("ModelEnumDescription[MODEL]:color", $this->memcache->data);
    }

    public function testForgetEnum(): void {
        EnumDescription::forget("color");
        $this->assertSame([ "ModelEnumDescription[MODEL]:color" ], $this->memcache->deleted);
    }

    public function testForgetWithoutMemcacheDoesNothing(): void {
        Configurator::$memcache = null;
        ClassDescription::forget("pages");
        EnumDescription::forget("color");
        $this->assertSame([], $this->memcache->deleted);
    }
}
