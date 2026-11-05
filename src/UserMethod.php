<?php
namespace API;

use API\Exceptions\UserMethodNotFoundException;

/**
 * Uzivatelske metody z tabulky `methods` (administrace: Model -> Metody).
 *
 * Vychozi beh je kod metody z DB pres eval - promenne z $arguments jsou v kodu metody jako lokalni promenne.
 *
 * Volitelne (config projektu `"methods" => [ "cache" => true ]`) se metoda nejdriv hleda v cache, kterou stavi
 * administrace pri ulozeni metody: tmp/cache/methods/GeneratedMethods.php, trida API\Generated\GeneratedMethods.
 * Bez eval a bez dotazu do DB. Generovany soubor ma ale strict_types a navratovy typ mixed: metoda, ktera nekde
 * nevraci hodnotu (napr. vetev bez return), nebo vola funkci PHP s jinym typem, v cache spadne na TypeError,
 * kdezto pres eval projde. Proto je cache zatim vypnuta, dokud ji administrace nebude generovat stejne jako eval.
 */
class UserMethod {
    // retezec, ne ::class - trida existuje jen kdyz administrace vygeneruje cache
    const GENERATED_CLASS = 'API\Generated\GeneratedMethods';

    /** maximalni hloubka skladani metod (`API.Method::Jina;` uvnitr metody) */
    const MAX_COMPOSITION_DEPTH = 10;

    /** @var bool generovana cache nactena (neuspech se nepamatuje - soubor muze vzniknout pozdeji, napr. v cron procesu) */
    private static bool $generatedLoaded = false;

    public static function get_method($method_name) {
        return self::replace_method_in_code(self::get_plain_method($method_name));
    }

    private static function get_plain_method($method_name): string {
        $method_name = (string)$method_name;

        $method = \API\Configurator::$connection->fetch("SELECT content FROM methods WHERE name = ?", $method_name);
        if (!empty($method)) return (string)$method["content"];

        // stare metody v souborech vedle tridy (basename - jmeno metody nesmi vest mimo slozku)
        $method_path = __DIR__ . "/Methods/" . basename($method_name) . ".php";
        if ($method_name !== "" && is_file($method_path)) return (string)file_get_contents($method_path);

        throw new UserMethodNotFoundException($method_name, "Method '$method_name' not found");
    }

    /**
     * Spusti metodu podle jmena.
     * @param string $method_name
     * @param array $arguments promenne dostupne v kodu metody (napr. [ "password" => ..., "hash" => ... ])
     * @return mixed navratova hodnota metody
     * @throws UserMethodNotFoundException
     */
    public static function run_method($method_name, array $arguments) {
        if (self::isCacheEnabled() && self::hasGeneratedMethod($method_name)) {
            return self::runGenerated($method_name, $arguments);
        }

        $__um_code = self::get_method($method_name);
        // $method zustava kvuli zpetne kompatibilite (kod metody ji mohl cist); spousti se ale $__um_code,
        // takze argument pojmenovany "method" uz nemuze podstrcit jiny kod
        $method = $__um_code;

        foreach ($arguments as $key => $val) {
            ${$key} = $val;
        }

        return eval($__um_code);
    }

    public static function replace_method_in_code($code) {
        return self::replaceComposition((string)$code, 0);
    }

    private static function replaceComposition(string $code, int $depth): string {
        if ($depth > self::MAX_COMPOSITION_DEPTH) {
            throw new \RuntimeException("Method composition is deeper than " . self::MAX_COMPOSITION_DEPTH . " (cycle API.Method::...?)");
        }

        $code = str_replace(["<?php", "?>"], "", $code);
        $res = preg_match_all("/API.Method::([A-Za-z_\-0-9]+);/", $code, $matches);

        if (empty($res)) return $code;

        for ($i = 0; $i < $res; $i++) {
            $meth = self::get_plain_method($matches[1][$i]);
            $code = str_replace($matches[0][$i], $meth, $code);
        }

        return self::replaceComposition($code, $depth + 1);
    }

    public static function run_code($code, array $arguments = []) {
        $__um_code = self::replace_method_in_code($code);

        foreach ($arguments as $key => $val) {
            ${$key} = $val;
        }

        return eval($__um_code);
    }

    /**
     * Existuje metoda (v DB, pripadne v zapnute generovane cache)?
     */
    public static function exists($method_name): bool {
        $method_name = (string)$method_name;
        if (self::isCacheEnabled() && self::hasGeneratedMethod($method_name)) return true;

        return !empty(\API\Configurator::$connection->fetch("SELECT id FROM methods WHERE name = ?", $method_name));
    }

    /**
     * Nazvy vsech metod v DB, serazene podle jmena.
     * @return string[]
     */
    public static function listAll(): array {
        $names = [];
        foreach (\API\Configurator::$connection->fetchAll("SELECT name FROM methods ORDER BY name") as $row) {
            $names[] = (string)$row["name"];
        }
        return $names;
    }

    /**
     * Zapnuta generovana cache metod? (config projektu: "methods" => [ "cache" => true ])
     */
    public static function isCacheEnabled(): bool {
        return !empty(\API\Configurator::$config["methods"]["cache"]);
    }

    /**
     * Cesta ke generovane cache metod (tmp projektu), null bez nastaveneho tmp.
     */
    public static function generatedCachePath(): ?string {
        $temp = \API\Configurator::$config["path"]["absolute"]["temp"] ?? "";
        if ($temp === "") return null;

        return rtrim($temp, "/\\") . "/cache/methods/GeneratedMethods.php";
    }

    private static function hasGeneratedMethod($method_name): bool {
        return self::ensureGeneratedLoaded() && method_exists(self::GENERATED_CLASS, (string)$method_name);
    }

    private static function runGenerated($method_name, array $arguments): mixed {
        // generovane metody maji podpis (mixed $value = null, array $context = []) a context rozbali do promennych
        $value = null;
        $context = $arguments;
        if (array_key_exists("value", $arguments)) {
            $value = $arguments["value"];
            unset($context["value"]);
        }

        return call_user_func([ self::GENERATED_CLASS, (string)$method_name ], $value, $context);
    }

    private static function ensureGeneratedLoaded(): bool {
        if (self::$generatedLoaded || class_exists(self::GENERATED_CLASS, false)) {
            return self::$generatedLoaded = true;
        }

        // soubor generuje administrace pro PHP 8 (typ mixed) - starsi PHP by na nem spadlo pri parsovani
        $path = self::generatedCachePath();
        if (PHP_VERSION_ID >= 80000 && $path !== null && is_file($path)) {
            require_once $path;
            self::$generatedLoaded = class_exists(self::GENERATED_CLASS, false);
        }

        return self::$generatedLoaded;
    }
}
