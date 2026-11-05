<?php

declare(strict_types=1);

namespace API\Frontend;

/**
 * Znacky do <head> stranky webu, sbirane behem vykreslovani (sablony page.php, kod modulu) a vypsane jednim volanim.
 *
 * Kazda znacka ma klic (title, meta:description, link:canonical, alternate:cs...). Na jeden klic plati jedna
 * hodnota: vyhraje vyssi priorita, pri stejne priorite posledni nastaveni. Kostra webu tak da z objektu stranky
 * vychozi hodnoty (PRIORITY_DEFAULT) a stranka nebo modul je prepise, i kdyz se hlavicka vykresluje az po obsahu.
 *
 * Escapovani dela knihovna: hodnoty se predavaji jako prosty text, JSON-LD jako pole.
 *
 * Pouziti:
 *   Head::title("Tricko s potiskem");                          // vychozi priorita stranky (PRIORITY_PAGE)
 *   Head::meta("description", $popis);
 *   Head::canonical("https://web.cz/eshop/tricko/", Head::PRIORITY_MODULE);
 *   Head::alternate("en", "https://web.cz/en/shop/t-shirt/");
 *   Head::jsonLd([ "@context" => "https://schema.org", "@type" => "Product", "name" => "Tricko" ], "product");
 *   Latte hlavicky: {\API\Frontend\Head::render()|noescape}   PHP: Head::print();
 */
final class Head {
    /** vychozi hodnoty kostry webu (objekt stranky) */
    public const int PRIORITY_DEFAULT = 0;
    /** sablona stranky (page.php) - vychozi priorita vsech metod */
    public const int PRIORITY_PAGE = 10;
    /** modul (Eshop, ...) - prepise stranku i kostru */
    public const int PRIORITY_MODULE = 20;

    private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** poradi skupin ve vystupu */
    private const array GROUPS = [ "title", "meta", "property", "link", "alternate", "jsonld", "raw" ];

    /** @var array<string, array{group: string, html: string, priority: int, seq: int}> klic -> znacka */
    private static array $tags = [];

    /** poradi nastaveni (pri stejne priorite vyhraje pozdejsi, ve vystupu drzi poradi prvniho nastaveni klice) */
    private static int $seq = 0;

    /** Titulek stranky (<title>) */
    public static function title(string $title, int $priority = self::PRIORITY_PAGE): void {
        self::put("title", "title", "<title>" . self::e($title) . "</title>", $priority);
    }

    /** <meta name="..." content="..."> - description, robots, keywords... */
    public static function meta(string $name, string $content, int $priority = self::PRIORITY_PAGE): void {
        $name = strtolower(trim($name));
        self::put("meta:$name", "meta", '<meta name="' . self::e($name) . '" content="' . self::e($content) . '">', $priority);
    }

    /** <meta property="..." content="..."> - Open Graph (og:title, og:image...) */
    public static function property(string $property, string $content, int $priority = self::PRIORITY_PAGE): void {
        $property = strtolower(trim($property));
        self::put("property:$property", "property", '<meta property="' . self::e($property) . '" content="' . self::e($content) . '">', $priority);
    }

    /** Kanonicka adresa stranky (<link rel="canonical">) */
    public static function canonical(string $url, int $priority = self::PRIORITY_PAGE): void {
        self::addLink("canonical", $url, [], "link:canonical", $priority, "link");
    }

    /**
     * Jazykova verze stranky (<link rel="alternate" hreflang="...">), jedna na jazyk; "x-default" pro vychozi.
     */
    public static function alternate(string $hreflang, string $url, int $priority = self::PRIORITY_PAGE): void {
        $hreflang = strtolower(trim($hreflang));
        self::addLink("alternate", $url, [ "hreflang" => $hreflang ], "alternate:$hreflang", $priority, "alternate");
    }

    /**
     * Obecny <link> (prev, next, preload, icon...).
     * @param array<string, string|int|bool|null> $attrs dalsi atributy (true = atribut bez hodnoty, null/false = vynechat)
     * @param string|null $key klic znacky; bez nej rel + href (stejny odkaz jen jednou)
     */
    public static function link(string $rel, string $href, array $attrs = [], ?string $key = null, int $priority = self::PRIORITY_PAGE): void {
        self::addLink($rel, $href, $attrs, $key, $priority, "link");
    }

    /** @param array<string, string|int|bool|null> $attrs */
    private static function addLink(string $rel, string $href, array $attrs, ?string $key, int $priority, string $group): void {
        $html = '<link rel="' . self::e(strtolower(trim($rel))) . '" href="' . self::e($href) . '"' . self::attrs($attrs) . '>';
        self::put($key ?? "link:" . strtolower($rel) . ":" . $href, $group, $html, $priority);
    }

    /**
     * Strukturovana data (<script type="application/ld+json">).
     * @param array<mixed> $data pole pro json_encode (napr. [ "@context" => "https://schema.org", "@type" => "Product" ])
     * @param string|null $key klic (stejny klic = prepis, napr. "product", "breadcrumb"); bez nej se pridava
     * @throws \JsonException data nejdou prevest na JSON
     */
    public static function jsonLd(array $data, ?string $key = null, int $priority = self::PRIORITY_PAGE): void {
        // JSON_HEX_TAG: "</script>" v datech nemuze ukoncit znacku
        $html = '<script type="application/ld+json">' . json_encode($data, self::JSON_FLAGS) . '</script>';
        self::put("jsonld:" . ($key ?? "#" . (self::$seq + 1)), "jsonld", $html, $priority);
    }

    /**
     * Surove HTML do hlavicky - posledni moznost, knihovna ho NEESCAPUJE (jen vlastni, duveryhodny obsah).
     * @param string|null $key klic (stejny klic = prepis); bez nej se pridava
     */
    public static function raw(string $html, ?string $key = null, int $priority = self::PRIORITY_PAGE): void {
        self::put("raw:" . ($key ?? "#" . (self::$seq + 1)), "raw", $html, $priority);
    }

    /** Odebere znacku podle klice (napr. "meta:robots"), jen kdyz ji nenastavil nekdo s vyssi prioritou */
    public static function remove(string $key, int $priority = self::PRIORITY_PAGE): void {
        if (isset(self::$tags[$key]) && self::$tags[$key]["priority"] > $priority) return;
        unset(self::$tags[$key]);
    }

    /** Je znacka nastavena? (klice: title, meta:description, property:og:title, link:canonical, alternate:cs...) */
    public static function has(string $key): bool {
        return isset(self::$tags[$key]);
    }

    /** HTML vsech znacek (jedna na radek), poradi: title, meta, property, link, alternate, jsonld, raw */
    public static function render(): string {
        $tags = self::$tags;
        uasort($tags, fn(array $a, array $b): int =>
            [ array_search($a["group"], self::GROUPS, true), $a["seq"] ] <=> [ array_search($b["group"], self::GROUPS, true), $b["seq"] ]);

        return implode("\n", array_column($tags, "html"));
    }

    /** Vypise render() (PHP sablony) */
    public static function print(): void {
        echo self::render();
    }

    /** Zapomene vsechny znacky (testy, dlouho bezici procesy, vic stranek v jednom pozadavku) */
    public static function reset(): void {
        self::$tags = [];
        self::$seq = 0;
    }

    private static function put(string $key, string $group, string $html, int $priority): void {
        self::$seq++;
        $current = self::$tags[$key] ?? null;
        if ($current !== null && $current["priority"] > $priority) return;

        self::$tags[$key] = [
            "group" => $group,
            "html" => $html,
            "priority" => $priority,
            // prepis drzi misto ve vystupu podle prvniho nastaveni klice
            "seq" => $current["seq"] ?? self::$seq,
        ];
    }

    /** @param array<string, string|int|bool|null> $attrs */
    private static function attrs(array $attrs): string {
        $out = "";
        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) continue;
            $name = preg_replace('/[^a-zA-Z0-9_:-]/', "", (string)$name) ?? "";
            if ($name === "") continue;
            $out .= $value === true ? " $name" : " $name=\"" . self::e((string)$value) . '"';
        }
        return $out;
    }

    private static function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, "UTF-8");
    }
}
