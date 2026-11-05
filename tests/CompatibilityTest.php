<?php

declare(strict_types=1);

use API\Configurator;
use PHPUnit\Framework\TestCase;

/**
 * Kontrakty, ktere 2.0 drzi kvuli projektum (UPGRADE-2.0.md, "Co zustane beze zmeny").
 */
final class CompatibilityTest extends TestCase {
    protected function tearDown(): void {
        \API\Globals::clear();
        Configurator::$memcache = null;
    }

    public function testOldGlobalClassNamesStillWork(): void {
        $this->assertSame(\API\Globals::class, (new \ReflectionClass("Globals"))->getName());
        $this->assertSame(\API\Localization::class, (new \ReflectionClass("Localization"))->getName());
        $this->assertSame(\API\L::class, (new \ReflectionClass("L"))->getName());
        $this->assertSame(\API\ArrayTokenScanner::class, (new \ReflectionClass("ArrayTokenScanner"))->getName());
        $this->assertSame(\API\Exceptions\UploadException::class, (new \ReflectionClass("UploadException"))->getName());
    }

    public function testGlobalsGetReturnsReference(): void {
        \Globals::set("head_data", []);
        \Globals::get("head_data")[] = "json-ld";

        $this->assertSame([ "json-ld" ], \API\Globals::get("head_data"));
        $this->assertNull(\Globals::get("missing"));
        $this->assertFalse(\Globals::is_set("missing"));
    }

    public function testMissingTranslationIsNullOrEmptyString(): void {
        Configurator::$memcache = new \Tests\Support\FakeMemcache();
        Configurator::$memcache->data["loc:cs_CZ"] = 1;   // preklady "nactene" - bez dotazu do DB
        Configurator::$memcache->data["loc:cs_CZ:hello"] = "Ahoj <b>@name</b>";

        $this->assertNull(\API\Localization::getText(null));
        $this->assertSame("", \API\Localization::getPlainText("missing"));
        $this->assertSame("Ahoj 5", \API\Localization::getPlainText("hello", [ "params" => [ "name" => 5 ] ]));
        $this->assertSame("vychozi", \L::t("missing", "vychozi"));
    }
}
