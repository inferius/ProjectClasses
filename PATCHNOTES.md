# Patch notes - michalosoft/project-classes

Vsechny zmeny balicku. Nova verze = nova sekce nahore (verze, datum, co se zmenilo a co musi projekt udelat).
Projekty: anoda (admin + web), cestadocloudu (admin + web), mytimi2.

## v1.4.1 - 2026-10-05

- `PlainUrl::permanentlyRedirectTo()`: lomitko na konec cesty se pridava pred `?query` / `#fragment`
  (drive `/stranka?a=1` -> `/stranka?a=1/`, parametr pak nesl lomitko).

## v1.4.0 - 2026-10-05

- `Menu`: upravy jednotlivych zaznamu zdroje - `config.overrides[id zaznamu]`:
  `hidden` (skryt), `labels[lang_id]` (vlastni text v jazyce), `ord` (poradi; zaznamy zdroje maji 10, 20, 30...),
  `parent` (id jine polozky menu - zaznam se zobrazi na konci jejich podpolozek misto ve zdroji).
  Prazdna polozka, do ktere se zaznamy presouvaji, se zobrazi jen kdyz neco obsahuje.
- `Menu`: seskupeni zaznamu zdroje podle atributu - `config.group_by`: `attr` (hodnota skupiny), `class`
  (trida skupin, nadpis ze zaznamu s id = hodnota), `label` (atribut nadpisu, lze `a|b`), `key`, `order`
  (razeni skupin podle atributu tridy, jinak podle poradi zaznamu). Skupiny jsou uzly typu `group`.
- `Menu`: `ctx["trace"]` + `trace()` - zaznamy kazdeho zdroje (vcetne skrytych a presunutych) pro editor.
- Uzly maji navic `ord` a u generovanych skupin `generated`.

## v1.3.1 - 2026-10-05

- `Menu`: text zaznamu (`object`) a zdroje ze tridy (`source_class`) muze mit nahradni atributy - `config.label`
  `"menu_text|name"` = prvni neprazdna hodnota (napr. text do menu, jinak nazev).

## v1.3.0 - 2026-10-05

- Nove `API\Frontend\Menu` - menu webu z editoru menu v administraci (tabulky `_front_menu`, `_front_menu_item`,
  `_front_menu_item_lang`; admin migrace `2026-10-05_03_menu_editor.php`).
  - `Menu::get("main", $ctx)` vrati strom uzlu `[id, type, label, url, title, target, icon, image, description, cls, key, row, children]`
    pro jazyk z kontextu; s memcache se strom cachuje (klic obsahuje cas posledni upravy menu, ulozeni v adminu ho zneplatni).
  - Typy polozek: `page`, `object`, `link`, `group`, `block`, `source_class` (zaznamy tridy: filtry, podminka, razeni,
    limit, mapovani atributu) a `source_sql` (jen SELECT, `START TRANSACTION READ ONLY`, na MariaDB `max_statement_time`,
    parametry `:lang_id`, `:lang_prefix`, `{$row.sloupec}`). Podrizene polozky zdroje se opakuji pod kazdym radkem
    a vidi jeho hodnoty jako `{$row.sloupec}` (napr. kategorie -> zaznamy kategorie).
  - Zavislosti na projektu dodava kontext: `page` (stranka podle text_id), `rows` (nacteni trid), `url`, `file_url`,
    `lang_prefix`; admin nahled navic `items` (neulozene polozky) a `debug` (chyby do `errors()`).
  - `Menu::checkSql()` a `Menu::checkCondition()` pro kontrolu v administraci.
- **Nasazeni:** composer update na webech i v administracich; v adminu migrace `2026-10-05_03` (a pripadne naplneni menu);
  web pouzije menu pres vlastni adapter (anoda: `webMenu()` ve `framework/php/functions.inc.php`).

## v1.2.2 - 2026-10-05

- `Localization`: preklad se cte z jednoho sloupce `content` (`_mct_translate_lang_data`).
  Puvodni `short_text` se pouzije jen kdyz `content` chybi - funguje pred i po migraci, ktera sloupce slouci
  (admin `migrations/2026-10-05_02_preklady_jeden_sloupec.php`: `content` MEDIUMTEXT, `short_text` smazan).
  Dotaz cte `fsld.*`, takze nezavisi na existenci `short_text`.
- `Localization::clear()`: bez memcache uz nespadne (`flush()` jen kdyz je memcache nastavena).
- **Nasazeni:** nejdriv `composer update michalosoft/project-classes` na webech i v administracich, pak migrace,
  pak vymazat cache (preklady a popisy trid jsou v memcache).

## v1.2.1 - 2026-10-03

- `PageTemplate`: CSS/JS sablon s `?v=<cas zmeny>` (`PageTemplate::assetVersion()`), spojene soubory
  v `CSS_JS_Cache` maji v nazvu i cas zmeny souboru - po nasazeni se nenacitaji stare verze z cache.

## v1.2.0 - 2026-10-03

- `PlainUrl::redirectAddSlash()` (puvodni `redirectWithoutSlash()` zustava jako deprecated alias),
  nove `redirectRemoveSlash()`.
- `Globals::get()` vraci referenci, nove `get_all()`, `clear()`, `is_set()`.
- `ClassDescription::hasAttribute()`.
- PHP 8.3 kompatibilita: deklarovane vlastnosti v `Users`, opraven `DataReader` (staticky pristup k instancni
  vlastnosti), chybejici `InvalidConfigException`, `FunctionCore::getMimeTypeByPath()` fallback na
  `mime_content_type`, type hinty `\Exception` ve vyjimkach.
- Podporovane PHP: 7.2 - 8.3.

## v1.1 a starsi

Bez patch notes - viz historie gitu (`git log v1.1`).
