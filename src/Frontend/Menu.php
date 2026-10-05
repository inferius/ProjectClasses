<?php

namespace API\Frontend;

/**
 * Menu webu z editoru menu v administraci.
 *
 * Tabulky (migrace adminu 2026-10-05_03_menu_editor):
 *   _front_menu            jednotliva menu (text_id: main, footer, ...)
 *   _front_menu_item       strom polozek (parent_id, ord, type, ref, ref_id, config JSON, ...)
 *   _front_menu_item_lang  popisek, URL, title a zverejneni pro jazyk
 *
 * Typy polozek:
 *   page          stranka podle text_id (popisek a URL dodava ctx "page"), config.no_url = jen rozbalovaci
 *   object        zaznam tridy (ref = trida, ref_id = id), config.label / config.url = atributy
 *   link          vlastni URL v jazyce
 *   group         nadpis / sloupec bez odkazu
 *   block         zastupce specialni sablony (key = ref)
 *   source_class  podpolozky ze tridy modelu (config: class, label, url, icon, image, key, description,
 *                 filters [{attr, op, value}], condition (vyvojar), order [{attr, dir}], limit)
 *   source_sql    podpolozky z SQL dotazu (config: sql, limit) - jen SELECT, sloupce label, url,
 *                 icon, image, description, key; parametry :lang_id, :lang_prefix, {$row.sloupec}
 *
 * Zdroj nahradi sebe sama vygenerovanymi polozkami. Jeho podrizene polozky v editoru se pouziji
 * pod kazdou vygenerovanou polozkou - v jejich textech, URL, podminkach a SQL je k dispozici
 * radek rodice jako {$row.sloupec} (tak lze napr. kategorie -> produkty kategorie).
 *
 * Vystup: seznam uzlu [id, type, label, url, title, target, icon, image, description, cls, key, row, children].
 *
 * Kontext ($ctx) - zavislosti na projektu dodava web / admin:
 *   lang_id       jazyk (vychozi Configurator::$currentLanguageId)
 *   page          fn(string $text_id, int $lang_id): ?array ["label" => ..., "url" => hotova URL]
 *   rows          fn(string $class, array $attrs, ?string $condition, array $order, int $limit, int $lang_id): array
 *                 radky jako ploche pole atribut => hodnota (vcetne "id")
 *   url           fn(string $url): string - relativni URL z dat -> URL odkazu (jazykovy prefix)
 *   file_url      fn($file): string - hodnota souboroveho atributu -> URL obrazku
 *   lang_prefix   prefix jazyka pro :lang_prefix v SQL
 *   cache         Memcache (vychozi Configurator::$memcache), cache_ttl
 *   items         (admin nahled) neulozene polozky misto nacteni z DB
 *   debug         chyby zdroju do errors() misto error_log
 */
final class Menu {
    public const TYPES = [ "page", "object", "link", "group", "block", "source_class", "source_sql" ];
    public const SOURCE_TYPES = [ "source_class", "source_sql" ];
    public const DEFAULT_LIMIT = 100;
    public const MAX_LIMIT = 500;
    private const MAX_DEPTH = 8;
    private const MAX_NODES = 3000;
    private const FILTER_OPS = [ "=", "!=", "<", ">", "<=", ">=", "like", "null", "notnull", "in" ];

    private $ctx;
    private $errors = [];
    private $nodes = 0;
    private $isMaria = null;

    /** Strom menu pro jazyk z kontextu (z cache, kdyz je memcache). Neexistujici menu = []. */
    public static function get(string $menu, array $ctx = []): array {
        return (new self($ctx))->build($menu);
    }

    public function __construct(array $ctx = []) {
        $this->ctx = $ctx + [
            "connection" => \API\Configurator::$connection,
            "lang_id" => \API\Configurator::$currentLanguageId,
            "page" => null,
            "rows" => null,
            "url" => fn($u) => (string)$u,
            "file_url" => fn($f) => is_array($f) || $f instanceof \ArrayAccess ? (string)($f["relative_path"] ?? "") : (string)$f,
            "lang_prefix" => "",
            "cache" => \API\Configurator::$memcache ?? null,
            "cache_ttl" => 86400,
            "items" => null,
            "debug" => false,
        ];
    }

    /** Chyby zdroju a polozek z posledniho build() (jen s debug). */
    public function errors(): array {
        return $this->errors;
    }

    public function build(string $menuTextId): array {
        $db = $this->ctx["connection"];
        $menu = $db->fetch("SELECT id, edited FROM _front_menu WHERE text_id = ?", $menuTextId);
        if (!$menu) return [];

        $lang = (int)$this->ctx["lang_id"];
        $preview = $this->ctx["items"] !== null;
        $cache = $preview || $this->ctx["debug"] ? null : $this->ctx["cache"];
        $key = "menu:" . $menuTextId . ":" . $lang . ":" . strtotime((string)$menu->edited);
        if ($cache) {
            $hit = $cache->get($key);
            if (is_array($hit)) return $hit;
        }

        $items = $preview ? $this->ctx["items"] : self::loadItems($db, (int)$menu->id, $lang);
        $byParent = [];
        foreach ($items as $it) {
            $byParent[(int)($it["parent_id"] ?? 0)][] = $it;
        }
        foreach ($byParent as &$list) {
            usort($list, fn($a, $b) => [(int)$a["ord"], (int)$a["id"]] <=> [(int)$b["ord"], (int)$b["id"]]);
        }
        unset($list);

        $tree = $this->children(0, $byParent, [], 0);
        if ($cache) $cache->set($key, $tree, 0, (int)$this->ctx["cache_ttl"]);
        return $tree;
    }

    /** Polozky menu s texty jazyka (format pro build i nahled adminu). */
    public static function loadItems($db, int $menuId, int $langId): array {
        $out = [];
        $rows = $db->fetchAll("SELECT i.*, l.label, l.url AS lang_url, l.title, l.lang_visible
            FROM _front_menu_item AS i
            LEFT JOIN _front_menu_item_lang AS l ON l.item_id = i.id AND l.lang_id = ?
            WHERE i.menu_id = ? ORDER BY i.parent_id, i.ord, i.id", $langId, $menuId);
        foreach ($rows as $r) {
            $it = $r instanceof \Nette\Database\Row ? iterator_to_array($r) : (array)$r;
            $it["config"] = self::decodeConfig($it["config"] ?? null);
            $out[] = $it;
        }
        return $out;
    }

    public static function decodeConfig($json): array {
        if (is_array($json)) return $json;
        $c = json_decode((string)$json, true);
        return is_array($c) ? $c : [];
    }

    // ------------------------------------------------------------------ sestaveni stromu

    private function children(int $parentId, array $byParent, array $rows, int $depth): array {
        $out = [];
        if ($depth > self::MAX_DEPTH) {
            $this->error($parentId, "příliš hluboké zanoření");
            return $out;
        }
        foreach ($byParent[$parentId] ?? [] as $it) {
            if (isset($it["enabled"]) && !(int)$it["enabled"]) continue;
            if (isset($it["lang_visible"]) && $it["lang_visible"] !== null && !(int)$it["lang_visible"]) continue;
            foreach ($this->item($it, $byParent, $rows, $depth) as $node) {
                if (++$this->nodes > self::MAX_NODES) {
                    $this->error((int)$it["id"], "menu má příliš mnoho položek");
                    return $out;
                }
                $out[] = $node;
            }
        }
        return $out;
    }

    /** Polozka -> uzly (zdroj vraci vice uzlu, skryta polozka zadny). */
    private function item(array $it, array $byParent, array $rows, int $depth): array {
        $id = (int)$it["id"];
        $type = (string)$it["type"];
        $cfg = self::decodeConfig($it["config"] ?? null);
        $row = $rows[0] ?? [];

        try {
            if ($type === "source_class" || $type === "source_sql") {
                $data = $type === "source_class" ? $this->classRows($cfg, $rows) : $this->sqlRows($cfg, $rows);
                $nodes = [];
                foreach ($data as $r) {
                    $node = $this->node($it, $cfg);
                    $node["label"] = trim(strip_tags((string)($r["label"] ?? "")));
                    $node["url"] = $this->linkUrl((string)($r["url"] ?? ""));
                    $node["icon"] = (string)($r["icon"] ?? "") ?: $node["icon"];
                    $node["image"] = $r["image"] ?? null;
                    $node["description"] = (string)($r["description"] ?? "");
                    $node["key"] = (string)($r["key"] ?? ($r["id"] ?? ""));
                    $node["row"] = $r;
                    $node["children"] = $this->children($id, $byParent, array_merge([$r], $rows), $depth + 1);
                    if ($node["label"] === "" && empty($node["children"])) continue;
                    $nodes[] = $node;
                }
                return $nodes;
            }

            $node = $this->node($it, $cfg);
            $label = $this->fill((string)($it["label"] ?? ""), $row);
            $langUrl = $this->fill((string)($it["lang_url"] ?? ""), $row);

            switch ($type) {
                case "page":
                    $page = is_callable($this->ctx["page"]) ? ($this->ctx["page"])((string)$it["ref"], (int)$this->ctx["lang_id"]) : null;
                    if (!$page && $label === "") throw new \RuntimeException("stránka '{$it["ref"]}' nenalezena");
                    $node["label"] = $label !== "" ? $label : (string)($page["label"] ?? "");
                    $node["url"] = $langUrl !== "" ? $this->linkUrl($langUrl) : (string)($page["url"] ?? "");
                    if (!empty($cfg["no_url"])) $node["url"] = "";
                    break;
                case "object":
                    $labelAttr = self::attrName($cfg["label"] ?? "name");
                    $urlAttr = self::attrName($cfg["url"] ?? "url.url");
                    $obj = $this->rows((string)$it["ref"], array_filter([ $labelAttr, $urlAttr ]), "`_mct_{$it["ref"]}`.`id` = " . (int)$it["ref_id"], [], 1)[0] ?? null;
                    if (!$obj) throw new \RuntimeException("záznam {$it["ref"]} #{$it["ref_id"]} nenalezen");
                    $node["label"] = $label !== "" ? $label : trim(strip_tags((string)($obj[$labelAttr] ?? "")));
                    $node["url"] = $langUrl !== "" ? $this->linkUrl($langUrl) : $this->linkUrl((string)($obj[$urlAttr] ?? ""));
                    $node["row"] = $obj;
                    break;
                case "link":
                    $node["label"] = $label;
                    $node["url"] = $this->linkUrl($langUrl);
                    if ($node["url"] === "" && $label === "") return [];
                    break;
                case "group":
                    $node["label"] = $label;
                    break;
                case "block":
                    $node["label"] = $label;
                    $node["key"] = (string)($cfg["key"] ?? $it["ref"] ?? "");
                    break;
                default:
                    throw new \RuntimeException("neznámý typ '$type'");
            }

            $node["children"] = $this->children($id, $byParent, $rows, $depth + 1);
            if ($node["label"] === "" && $type !== "block" && empty($node["children"])) return [];
            return [ $node ];
        }
        catch (\Throwable $e) {
            $this->error($id, $e->getMessage() !== "" ? $e->getMessage() : get_class($e) . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")");
            return [];
        }
    }

    private function node(array $it, array $cfg): array {
        return [
            "id" => (int)$it["id"],
            "type" => (string)$it["type"],
            "label" => "",
            "url" => "",
            "title" => (string)($it["title"] ?? ""),
            "target" => !empty($it["target_blank"]) ? "_blank" : null,
            "icon" => (string)($it["icon"] ?? ""),
            "image" => null,
            "description" => "",
            "cls" => (string)($it["css_class"] ?? ""),
            // staticka polozka: config.key (napr. data-id rozbalovaciho menu), jinak ref; zdroj: klic z radku
            "key" => in_array((string)$it["type"], self::SOURCE_TYPES, true) ? "" : (string)($cfg["key"] ?? ($it["ref"] ?? "")),
            "row" => null,
            "children" => [],
        ];
    }

    /** URL z dat -> odkaz: absolutni (http:, mailto:, /, #) beze zmeny, relativni pres ctx url (jazykovy prefix). */
    private function linkUrl(string $url): string {
        $url = trim($url);
        if ($url === "") return "";
        if (preg_match('~^([a-z][a-z0-9+.-]*:|/|#)~i', $url)) return $url;
        return (string)($this->ctx["url"])($url);
    }

    /** {$row.sloupec} v textu -> hodnota z radku rodicovskeho zdroje. */
    private function fill(string $text, array $row): string {
        if ($text === "" || strpos($text, '{$row.') === false) return $text;
        return preg_replace_callback('/\{\$row\.([A-Za-z0-9_.]+)\}/', fn($m) => (string)(is_array($row[$m[1]] ?? null) ? "" : ($row[$m[1]] ?? "")), $text);
    }

    private function error(int $itemId, string $msg): void {
        $text = "Položka #$itemId: $msg";
        if ($this->ctx["debug"]) $this->errors[] = $text;
        else error_log("Menu: " . $text);
    }

    // ------------------------------------------------------------------ zdroj: trida modelu

    private function rows(string $class, array $attrs, ?string $condition, array $order, int $limit): array {
        if (!is_callable($this->ctx["rows"])) throw new \RuntimeException("načítání tříd není nastavené");
        if (!preg_match('/^\w+$/', $class)) throw new \RuntimeException("neplatná třída '$class'");
        return (array)($this->ctx["rows"])($class, array_values(array_unique($attrs)), $condition, $order, $limit, (int)$this->ctx["lang_id"]);
    }

    private function classRows(array $cfg, array $rows): array {
        $class = (string)($cfg["class"] ?? "");
        $map = [];
        foreach ([ "label", "url", "icon", "image", "key", "description" ] as $k) {
            $a = self::attrName($cfg[$k] ?? ($k === "url" ? "url.url" : ""));
            if ($a !== "") $map[$k] = $a;
        }
        if (empty($map["label"])) throw new \RuntimeException("zdroj nemá atribut popisku");

        $parts = [];
        foreach ((array)($cfg["filters"] ?? []) as $f) {
            $parts[] = $this->filterSql($class, (array)$f, $rows);
        }
        if (trim((string)($cfg["condition"] ?? "")) !== "") {
            $parts[] = "(" . $this->bindRow(self::checkCondition((string)$cfg["condition"]), $rows) . ")";
        }
        $order = [];
        foreach ((array)($cfg["order"] ?? []) as $o) {
            $a = self::attrName($o["attr"] ?? "");
            if ($a !== "") $order[] = [ "attrName" => $a, "direction" => strtolower((string)($o["dir"] ?? "asc")) === "desc" ? "desc" : "asc" ];
        }
        $limit = self::limit($cfg["limit"] ?? null);

        $data = $this->rows($class, array_values($map), $parts ? implode(" AND ", $parts) : null, $order, $limit);
        $out = [];
        foreach ($data as $r) {
            $n = $r;
            foreach ($map as $k => $a) $n[$k] = $r[$a] ?? null;
            if (isset($map["image"])) $n["image"] = ($this->ctx["file_url"])($r[$map["image"]] ?? null) ?: null;
            $out[] = $n;
        }
        return $out;
    }

    /** Filtr na sloupec hlavni tabulky tridy (_mct_<trida>); jazykove atributy jen pres podminku. */
    private function filterSql(string $class, array $f, array $rows): string {
        $attr = self::attrName($f["attr"] ?? "");
        if ($attr === "" || strpos($attr, ".") !== false) throw new \RuntimeException("neplatný atribut filtru");
        $op = strtolower((string)($f["op"] ?? "="));
        if (!in_array($op, self::FILTER_OPS, true)) throw new \RuntimeException("neplatný operátor '$op'");
        $col = "`_mct_{$class}`.`$attr`";
        if ($op === "null") return "$col IS NULL";
        if ($op === "notnull") return "$col IS NOT NULL";
        $value = $this->fill((string)($f["value"] ?? ""), $rows[0] ?? []);
        if ($op === "in") {
            $vals = array_map(fn($v) => $this->quote(trim($v)), array_filter(explode(",", $value), fn($v) => trim($v) !== ""));
            return $vals ? "$col IN (" . implode(", ", $vals) . ")" : "0";
        }
        return "$col " . strtoupper($op) . " " . $this->quote($value);
    }

    // ------------------------------------------------------------------ zdroj: SQL

    private function sqlRows(array $cfg, array $rows): array {
        $sql = self::checkSql((string)($cfg["sql"] ?? ""));
        $params = [];
        $row = $rows[0] ?? [];
        $sql = preg_replace_callback('/\{\$row\.([A-Za-z0-9_.]+)\}|:lang_id\b|:lang_prefix\b/', function ($m) use (&$params, $row) {
            if ($m[0] === ":lang_id") $params[] = (int)$this->ctx["lang_id"];
            else if ($m[0] === ":lang_prefix") $params[] = (string)$this->ctx["lang_prefix"];
            else $params[] = is_array($row[$m[1]] ?? null) ? null : ($row[$m[1]] ?? null);
            return "?";
        }, $sql);
        $limit = self::limit($cfg["limit"] ?? null);
        $sql = "SELECT * FROM ($sql) AS menu_source LIMIT $limit";

        $db = $this->ctx["connection"];
        if ($this->isMaria === null) {
            $this->isMaria = stripos((string)$db->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), "mariadb") !== false;
        }
        if ($this->isMaria) $sql = "SET STATEMENT max_statement_time = 3 FOR " . $sql;

        // jen cteni: transakce READ ONLY (zapis by DB odmitla), mimo uz bezici transakci
        $own = !$db->getPdo()->inTransaction();
        if ($own) $db->query("START TRANSACTION READ ONLY");
        try {
            $result = $db->fetchAll($sql, ...$params);
        }
        finally {
            if ($own) $db->query("COMMIT");
        }
        $out = [];
        foreach ($result as $r) {
            $out[] = $r instanceof \Nette\Database\Row ? iterator_to_array($r) : (array)$r;
        }
        return $out;
    }

    /** Kontrola SQL zdroje: jeden prikaz SELECT / WITH bez zapisu a zamku. Vraci dotaz bez ; na konci. */
    public static function checkSql(string $sql): string {
        $sql = rtrim(trim($sql), "; \t\r\n");
        if ($sql === "") throw new \RuntimeException("prázdný SQL dotaz");
        if (!preg_match('/^(SELECT|WITH)\b/i', $sql)) throw new \RuntimeException("SQL zdroj musí začínat SELECT nebo WITH");
        $plain = self::withoutStrings($sql);
        if (strpos($plain, ";") !== false) throw new \RuntimeException("SQL zdroj smí obsahovat jen jeden příkaz");
        if (preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE|@)|FOR\s+UPDATE|LOCK\s+IN\s+SHARE|SLEEP\s*\(|BENCHMARK\s*\(|LOAD_FILE\s*\(|GET_LOCK\s*\(|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|GRANT|SET\s+@)\b/i', $plain)) {
            throw new \RuntimeException("SQL zdroj smí jen číst data");
        }
        return $sql;
    }

    /** Kontrola podminky zdroje tridy (vyvojar): bez dalsich prikazu a zapisu. */
    public static function checkCondition(string $cond): string {
        $plain = self::withoutStrings($cond);
        if (strpos($plain, ";") !== false || preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|SLEEP|BENCHMARK|UNION)\b/i', $plain)) {
            throw new \RuntimeException("podmínka smí obsahovat jen výraz WHERE");
        }
        return $cond;
    }

    private static function withoutStrings(string $sql): string {
        return preg_replace([ "/'(?:[^'\\\\]|\\\\.)*'/s", '/"(?:[^"\\\\]|\\\\.)*"/s', '/--[^\n]*/', '~/\*.*?\*/~s' ], "''", $sql);
    }

    /** {$row.sloupec} v podmince -> hodnota v uvozovkach. */
    private function bindRow(string $cond, array $rows): string {
        $row = $rows[0] ?? [];
        $cond = preg_replace_callback('/\{\$row\.([A-Za-z0-9_.]+)\}/', fn($m) => $this->quote(is_array($row[$m[1]] ?? null) ? null : ($row[$m[1]] ?? null)), $cond);
        return str_replace(":lang_id", (string)(int)$this->ctx["lang_id"], $cond);
    }

    private function quote($v): string {
        if ($v === null) return "NULL";
        return $this->ctx["connection"]->getPdo()->quote((string)$v);
    }

    private static function attrName($a): string {
        $a = trim((string)$a);
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $a) ? $a : "";
    }

    private static function limit($v): int {
        $n = (int)$v;
        if ($n <= 0) $n = self::DEFAULT_LIMIT;
        return min($n, self::MAX_LIMIT);
    }
}
