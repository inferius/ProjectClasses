<?php

use API\Configurator;
use API\UserMethod;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeConnection;

final class UserMethodTest extends TestCase {
    /** @var FakeConnection */
    private \Tests\Support\FakeConnection $db;

    /** @var array<string, string> jmeno -> kod metody v DB */
    private array $methods = [
        "Add" => 'return $a + $b;',
        "Base" => '$x = $a + 1;',
        "Twice" => 'API.Method::Base; return $x * 2;',
        "LoopA" => 'API.Method::LoopB;',
        "LoopB" => 'API.Method::LoopA;',
    ];

    protected function setUp(): void {
        $methods = $this->methods;
        $this->db = (new FakeConnection())
            ->on("SELECT content FROM methods WHERE name = ?", fn(array $p): ?array => isset($methods[$p[0]]) ? [ "content" => $methods[$p[0]] ] : null)
            ->on("SELECT id FROM methods WHERE name = ?", fn(array $p): ?array => isset($methods[$p[0]]) ? [ "id" => 1 ] : null)
            ->on("SELECT name FROM methods", function () use ($methods): array {
                $names = array_keys($methods);
                sort($names);
                return array_map(fn(string $n): array => [ "name" => $n ], $names);
            });

        Configurator::$connection = $this->db;
        Configurator::$config = [];
    }

    protected function tearDown(): void {
        Configurator::$connection = null;
        Configurator::$config = [];
    }

    public function testRunsMethodFromDbWithArguments(): void {
        $this->assertSame(5, UserMethod::run_method("Add", [ "a" => 2, "b" => 3 ]));
    }

    public function testMethodNameGoesAsQueryParameter(): void {
        try {
            UserMethod::run_method("x' OR '1'='1", []);
            $this->fail("ocekavana UserMethodNotFoundException");
        }
        catch (\API\Exceptions\UserMethodNotFoundException $e) {
            $this->assertSame("x' OR '1'='1", $e->methodName);
        }

        $call = $this->db->calls[0];
        $this->assertStringNotContainsString("OR", $call["sql"]);
        $this->assertSame([ "x' OR '1'='1" ], $call["params"]);
    }

    public function testArgumentNamedMethodCannotReplaceCode(): void {
        $this->assertSame(2, UserMethod::run_method("Add", [ "a" => 1, "b" => 1, "method" => "return 99;" ]));
    }

    public function testComposedMethodsAreInlined(): void {
        $this->assertSame(4, UserMethod::run_method("Twice", [ "a" => 1 ]));
    }

    public function testCyclicCompositionStops(): void {
        $this->expectException(\RuntimeException::class);
        UserMethod::run_method("LoopA", []);
    }

    public function testRunCodeSeesArguments(): void {
        $this->assertSame("AB", UserMethod::run_code('return $value . $context;', [ "value" => "A", "context" => "B" ]));
    }

    public function testGeneratedCacheIsOffByDefault(): void {
        $this->assertFalse(UserMethod::isCacheEnabled());
        $this->assertSame(5, UserMethod::run_method("Add", [ "a" => 2, "b" => 3 ]));
    }

    public function testGeneratedCacheIsUsedWhenEnabled(): void {
        Configurator::$config = [ "methods" => [ "cache" => true ] ];

        $this->assertSame("cache:5", UserMethod::run_method("Add", [ "a" => 2, "b" => 3 ]));
        $this->assertSame("v", UserMethod::run_method("Echo_value", [ "value" => "v" ]));
        $this->assertSame([], $this->db->calls, "z cache bez dotazu do DB");
    }

    public function testExistsAndListAll(): void {
        $this->assertTrue(UserMethod::exists("Add"));
        $this->assertFalse(UserMethod::exists("Missing"));
        $this->assertSame([ "Add", "Base", "LoopA", "LoopB", "Twice" ], UserMethod::listAll());
    }

    public function testGeneratedCachePathFromConfig(): void {
        $this->assertNull(UserMethod::generatedCachePath());

        Configurator::$config = [ "path" => [ "absolute" => [ "temp" => "/srv/web/tmp/" ] ] ];
        $this->assertSame("/srv/web/tmp/cache/methods/GeneratedMethods.php", UserMethod::generatedCachePath());
    }
}
