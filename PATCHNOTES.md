# Patch notes - michalosoft/project-classes

Vsechny zmeny balicku. Nova verze = nova sekce nahore (verze, datum, co se zmenilo a co musi projekt udelat).
Projekty: anoda (admin + web), cestadocloudu (admin + web), mytimi2.

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
