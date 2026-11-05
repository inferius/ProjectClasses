<?php

use API\Configurator;
use API\Model\ClassDescription;
use API\Model\EnumDescription;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeMemcache;

final class CacheKeyTest extends TestCase {
    /** @var FakeMemcache */
    private \Tests\Support\FakeMemcache $memcache;

    protected function setUp(): void {
        $this->memcache = new FakeMemcache();
        Configurator::$memcache = $this->memcache;
        Configurator::$cachePrefix = "";
    }

    protected function tearDown(): void {
        Configurator::$memcache = null;
        Configurator::$cachePrefix = "";
    }

    public function testKeyHasLibraryVersion(): void {
        $this->assertSame("pc2:ModelClassDescription[MODEL]:pages", Configurator::cacheKey("ModelClassDescription[MODEL]:pages"));
    }

    public function testKeyWithProjectPrefix(): void {
        Configurator::$cachePrefix = "atoda";
        $this->assertSame("pc2:atoda:ModelClassDescription[MODEL]:pages", Configurator::cacheKey("ModelClassDescription[MODEL]:pages"));
    }

    public function testForgetDeletesNewAndLegacyClassKeys(): void {
        Configurator::$cachePrefix = "atoda";
        ClassDescription::forget("pages");

        $this->assertSame([ "pc2:atoda:ModelClassDescription[MODEL]:pages", "pc2:atoda:ModelClassDescription:pages", "ModelClassDescription:pages" ], $this->memcache->deleted);
    }

    public function testForgetWithoutPrefixDeletesEachKeyOnce(): void {
        ClassDescription::forget("pages");
        $this->assertSame([ "pc2:ModelClassDescription[MODEL]:pages", "pc2:ModelClassDescription:pages", "ModelClassDescription:pages" ], $this->memcache->deleted);
    }

    public function testLegacyDescriptionUsesPrefixedKey(): void {
        Configurator::$cachePrefix = "atoda";
        Configurator::$connection = (new \Tests\Support\FakeConnection())
            ->on("SELECT * FROM model_classes", fn(): array => [ "class_name" => "Stranky", "text_id" => "pages", "table_name" => "crm_pages" ])
            ->on("SELECT mat.*", fn(): array => []);

        $data = \API\ClassDescription::get("pages");

        $this->assertSame("crm_pages", $data["table"]);
        $this->assertSame([ "pc2:atoda:ModelClassDescription:pages" ], array_keys($this->memcache->data));
        Configurator::$connection = null;
    }

    public function testEnumCacheMissDoesNotWarn(): void {
        Configurator::$connection = (new \Tests\Support\FakeConnection())
            ->on("SELECT * FROM model_enums", fn(): array => [ "id" => 1 ])
            ->on("SELECT text_id, lockey", fn(): array => []);

        $warnings = 0;
        set_error_handler(function ($severity) use (&$warnings): true {
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
        $this->assertArrayHasKey("pc2:ModelEnumDescription[MODEL]:color", $this->memcache->data);
    }

    public function testForgetEnum(): void {
        EnumDescription::forget("color");
        $this->assertSame([ "pc2:ModelEnumDescription[MODEL]:color" ], $this->memcache->deleted);
    }

    public function testForgetWithoutMemcacheDoesNothing(): void {
        Configurator::$memcache = null;
        ClassDescription::forget("pages");
        EnumDescription::forget("color");
        $this->assertSame([], $this->memcache->deleted);
    }
}
