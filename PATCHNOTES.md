# Patch notes - michalosoft/project-classes

Vsechny zmeny balicku. Nova verze = nova sekce nahore (verze, datum, co se zmenilo a co musi projekt udelat).
Projekty: EasyAdmin (administrace a kostra webu; administrace projektu dostavaji knihovnu s vydanim jadra),
anoda (admin + web), cestadocloudu (admin + web), lapenna (admin + web). mytimi2 knihovnu nepouziva.

## v2.0.0 - ve vyvoji (master; opravy 1.x ve vetvi 1.x)

Prechod a kontrakty: [UPGRADE-2.0.md](UPGRADE-2.0.md). Hotovy zaklad (alpha):

- PHP 8.3+ (`composer.json`), `nette/database ^3.2`, `nette/utils ^4.0`, `imagine/imagine ^1.3`.
- `declare(strict_types=1)` ve vsech souborech, typy z Rectoru (sada PHP 8.3 a typove deklarace) rucne
  zkontrolovane, `readonly` v modelu, `#[\Override]`, typovane konstanty. PHPStan uroven 5 bez chyb typu (zbyva
  ~50 nalezu mrtveho kodu a zbytecnych kontrol), cil uroven 6.
- Globalni `Globals`, `Localization`, `L`, `ArrayTokenScanner` jsou v `API\`, `UploadException`
  v `API\Exceptions\`; stare nazvy funguji pres alias (`src/aliases.php`, composer "files").
- Odstraneno: `PlainUrl::redirectWithoutSlash()`, `PlainUrlToken::slash_at_end()`, `PageTemplate::load_css_js()`,
  `DataReader::getClassDataInner2()`, tridy `API\Frontend\DataReader`, `API\Model\DataLoader`,
  `API\DateTimeConfigurator`, `API\FunctionLine`.
- `BaseObject`: id `int|string|null` (ciselny retezec se prevede, neciselny = `ObjectNotFoundException`),
  `getId(): ?int` (u noveho objektu null, drive TypeError), `getCreated()` a `edited` jako DateTime ze sloupce
  datetime (drive false), `getObjectByAttr()` vraci `static` a lokalizovany atribut hleda v jazykove tabulce
  (drive v hlavni - dotaz padal) a u neznameho atributu vraci null, jazyk bez `Configurator::$currentLanguageId`
  = `BaseObject::DEFAULT_LANGUAGE` (2), id po vlozeni je int.
- `Configurator`: typy (`?int $currentLanguageId`, `?int $editLanguageId`, `?string $locale` + `locale()` s vychozim
  `DEFAULT_LOCALE` - weby ho plni ze session, kde muze chybet, `array $config`...),
  spojeni, memcache, presenter a funkce bez nativniho typu (@var). Klice memcache maji verzi:
  `Configurator::cacheKey()` = `pc2:[prefix:]klic` - popisy trid z 1.x se do typovanych trid nerozbaluji.
- `Localization::getText()` vraci `?string` (chybejici preklad null, drive i false z memcache),
  `getPlainText()` u chybejiciho prekladu "", parametry `@jmeno` prevadi na retezec (cislo drive TypeError).
- Opravy chyb, ktere byly i v 1.x: `PlainUrlToken::groups()` mel navratovy typ int a vracel pole (TypeError),
  `Functions\Argument` bez `Configurator::$replaceTextFnc` volal nedefinovanou promennou, poradi posluchacu
  udalosti (`EventManager`) radil porovnavac vracejici bool, `Users::createUser()` a `BaseObject::save()`
  predavaly id z `getInsertId()` jako retezec, `FunctionCore::setMemCache()` predaval null do Memcache::set.
- Nove `API\Frontend\Head`: znacky do `<head>` sbirane behem vykreslovani (sablony, moduly) a vypsane jednim
  volanim - `title()`, `meta()`, `property()`, `canonical()`, `alternate()` (hreflang), `link()`, `jsonLd()`, `raw()`,
  `remove()`, `has()`, `render()`, `print()`, `reset()`. Jedna hodnota na klic, vyhraje vyssi priorita
  (`PRIORITY_DEFAULT` kostra, `PRIORITY_PAGE` stranka, `PRIORITY_MODULE` modul), pri shode posledni. Escapuje
  knihovna, JSON-LD s `JSON_HEX_TAG`. Pozadavek vlakna jadra pro SEO hlavicky stranek modulu (Eshop).
- Code review 2.0: s `strict_types` uz PHP samo neprevede hodnoty z DB pro vestavene funkce - novy `API\Utils\Value`
  (`toNumber()`, `toTimestamp()`) v `toFormat()` u `NumberAttributeValue`, `DateTimeAttributeValue` a `SettingItem`
  (DECIMAL jako retezec do `number_format()`, Nette DateTime do `strtotime()` - jinak TypeError; prazdne datum = "").
  Neznama trida v `Model\ClassDescription::get()` = `InvalidArgumentException` s nazvem tridy (drive TypeError).
  `new PlainUrl()` bez adresy bere `REQUEST_URI` (drive se hned prepsalo null). `PostprocessData::getAllowedMimes()`
  testovala nedefinovanou promennou a vracela vzdy [] (povolene MIME typy se nepouzily). `UploadException` prevadi kod na int.
- Testy: PHPUnit 11 (`tests/*Test.php`), 108 testu bez databaze (PHP 8.3 i 8.5).

Zbyva do 2.0.0: typy vsech parametru (PHPStan 6), opravneni v knihovne, `DataReader` misto `getFullDataFromClass`,
`Users` v session jen id a token, `rector-2.0.php` pro projekty, integracni testy s MariaDB a CI.

## v1.8.0 - 2026-11-05

Zpetne kompatibilni priprava na 2.0 (PHP 8.3, typy, opravneni v knihovne) - co 2.0 zmeni a cim to nahradit:
[UPGRADE-2.0.md](UPGRADE-2.0.md).

- `UserMethod`: nazev metody jde do dotazu jako parametr (drive se skladal do SQL retezce). Argument pojmenovany
  `method` uz nemuze podstrcit jiny kod. Skladani metod `API.Method::Jina;` ma limit hloubky 10 (cyklus drive
  vycerpal zasobnik). Nove `exists()`, `listAll()`, `isCacheEnabled()`, `generatedCachePath()`.
- `UserMethod`: volitelna cache metod, kterou stavi administrace (`tmp/cache/methods/GeneratedMethods.php`,
  `API\Generated\GeneratedMethods`) - bez eval a bez dotazu do DB. **Vychozi vypnuto**, zapina config projektu
  `"methods" => [ "cache" => true ]`. Generovany soubor ma `strict_types` a navratovy typ `mixed`: metoda, ktera
  v nektere vetvi nevraci hodnotu, v cache spadne na TypeError (napr. `GetLoginCookieValidLength` ze zakladni
  instalace: `else strtotime("+365 days");` bez `return` - trvale prihlaseni). Zapnout az po uprave generatoru v jadru.
  Na PHP 7 se cache nenacita (soubor je pro PHP 8).
- `Users::getGroupIds()` (int[], perm_groups.id) a `Users::isSuperAdmin()` (`users_data.is_super_admin` z modulu
  opravneni; bez sloupce false).
- `Configurator::$cachePrefix` a `Configurator::cacheKey()`: prefix klicu memcache pro popisy trid a vyctu, kdyz vic
  projektu sdili jednu memcache. Prazdny (vychozi) = klice beze zmeny.
- `ClassDescription::forget($textId)` smaze popis tridy z memcache (klic `ModelClassDescription[MODEL]:` i zastaraly
  `ModelClassDescription:`, ten i bez prefixu - sklada ho kopie `getClassDescription()` ve frameworku administrace),
  `EnumDescription::forget($textId)` popis vyctu.
- `EnumDescription::get()` uz nevaruje "Deserialize EnumDescription not working" pri prazdne cache.
- PHP 8.4+: vyjimky (`$previous`) a `DataReaderOrder::addOrder()` maji u parametru s vychozim null explicitni `?`
  (PHP 8.4 hlasilo "Implicitly marking parameter as nullable is deprecated" pri kazdem vytvoreni vyjimky).
- Varovani `E_USER_DEPRECATED` (kazde jednou za pozadavek, `API\Utils\Deprecation`) u API, ktere 2.0 odstrani:
  `PlainUrl::redirectWithoutSlash()`, `PlainUrlToken::slash_at_end()`, `PageTemplate::load_css_js()`,
  `DataReader::getClassDataInner2()` a nepouzivane tridy `API\Frontend\DataReader`, `API\Model\DataLoader`,
  `API\DateTimeConfigurator`, `API\FunctionLine` (varovani pri nacteni).
- `composer.json`: `php >=7.4` (`Menu` pouziva arrow funkce od 1.3), `phpunit/phpunit ^9.6`; testy bez databaze
  (`phpunit.xml.dist`, `tests/Support`), `vendor/bin/phpunit`.

Projekt: `composer update michalosoft/project-classes`. Pak:
- Administrace (jadro, `pages.inc.php:5`) vola `$plainurl->redirectWithoutSlash()` - varovani pri kazdem pozadavku,
  nahradit `redirectAddSlash()`.
- Administrace po zmene tridy maze jen klic `ModelClassDescription:` (`api/Handlers/Model/ClassesHandler.php`,
  `webapi/model/classes.php`), klic `[MODEL]`, ktery cte `BaseObject`, zustava stary - volat
  `\API\Model\ClassDescription::forget($textId)`. `Configurator::$cachePrefix` nastavit az potom.

## v1.7.3 - 2026-11-05

- `PageTemplate::createOptiScriptLoad()` (spojene CSS/JS stranky v `tmp/CSS_JS_Cache`): CSS a JS se kontroluji a zapisuji
  zvlast. Drive se oba soubory vytvorily jen kdyz chybelo CSS - po nasazeni noveho `page.js` (CSS beze zmeny) dostal JS
  novy nazev, ale nevytvoril se: 404 a stranka bez svych funkci (Atoda: tlacitko Ulozit u oboru pece,
  `saveReload is not defined`). Projevi se jen bez debug modu (v debug se soubory zapisuji pokazde).
- Chybejici adresar `CSS_JS_Cache` se zalozi, soubory se zapisuji pres docasny soubor a prejmenovani.

Projekt: `composer update michalosoft/project-classes`; uz poskozene stranky opravi "Vymazat cache" (smaze `tmp/CSS_JS_Cache`).

## v1.7.2 - 2026-10-27

- `PageTemplate` (koreny `addTemplateRoot`): sablona z korene se vybere jen kdyz adresar stranky obsahuje `page.latte`
  nebo `page.php`. Drive stacil adresar - projekt, ktery prepsal jen podstranku (`project/templates/pages/x/detail`),
  tim zakryl i stranku `pages/x` z jadra a ta se vykreslila prazdna (Atoda: seznam Stranky `/s-f/d-cms/`).

Projekt: jen `composer update michalosoft/project-classes`.

## v1.7.1 - 2026-10-23

- `FileAttributeValue::regenerateImageVariants()` bez fatalni chyby pri ukonceni (objekt bez konstruktoru padal
  v destruktoru) - tvorba variant obrazku je ted staticka (`defaultFilePostProcess`, `$image_extension`, `$image_size`).

Projekt: jen `composer update michalosoft/project-classes`.

## v1.7.0 - 2026-10-23

- Kvalita variant obrazku (srcset) jde nastavit v config.inc.php projektu:
  `"image_quality" => [ "avif_quality" => 60, "webp_quality" => 80, "jpeg_quality" => 85, "png_compression_level" => 3 ]`
  (jen znamé klice, chybejici = vychozi). Vychozi hodnoty `FileAttributeValue::CONVERT_DEFAULTS`: **AVIF 60** (drive 85),
  **WebP 80** (drive 85), JPEG 85 a PNG 3 beze zmeny. AVIF 85 byl vetsi nez JPEG 85, AVIF 60 je zhruba o polovinu
  mensi pri srovnatelne kvalite (fotka 1200 px: 335 kB -> 150 kB).
- `FileAttributeValue::convertConfig(array $defaults)` - kvalita podle configu (pouziva i vodoznak a maska,
  ty maji dal vychozi 90).
- `FileAttributeValue::regenerateImageVariants(int $fileId)` - znovu vytvori varianty obrazku z fp_final_files
  (stare smaze, prepise format_info), napr. po zmene kvality.

Projekt: `composer update michalosoft/project-classes`. Nove nahrane obrazky maji mensi varianty hned; stavajici se
zmensi az pregenerovanim (`regenerateImageVariants` pro kazdy obrazek z fp_final_files).

## v1.6.0 - 2026-10-23

- `PageTemplate::addTemplateRoot($absolute, $relative, $priority = false)`: s `$priority = true` ma sablona z korene
  prednost i pred hlavnim adresarem (vrstva projektu nad jadrem v EasyAdminu - `project/templates`). Bez parametru
  beze zmeny (moduly - jen sablony, ktere jadro nema).

Projekt: jen `composer update michalosoft/project-classes`.

## v1.5.0 - 2026-10-23

- `PageTemplate::addTemplateRoot($absolute, $relative)`: dalsi koren sablon - sablona, ktera v hlavnim adresari
  (`configure`) neexistuje, se hleda v pridanych korenech (v poradi pridani). Hlavni adresar ma vzdy prednost.
  Pouziti: moduly administrace z registru se strankami v `modules/<id>/templates` (EasyAdmin `adminModulesBoot`).
  Bez volani se nic nemeni.

Projekt: jen `composer update michalosoft/project-classes`.

## v1.4.4 - 2026-10-23

- `Globals::get()`: chybejici klic vraci null bez Notice "Only variable references should be returned by reference"
  (funkce vraci referenci, drive vracela vyraz). Klic se nezaklada - `is_set()` dal vraci false; zapis pres
  referenci (`Globals::get("head_data")[] = ...`) u existujiciho klice funguje jako drive.

Projekt: jen `composer update michalosoft/project-classes`. Obchazeni pres `Globals::get_all()[...] ?? null` uz neni potreba.

## v1.4.3 - 2026-10-23

- `FileAttributeValue`: SVG uz neni mezi rastrovymi obrazky - ulozeni objektu s SVG v souborovem atributu
  (i nahrani SVG v administraci) padalo na "Unable to open image" (zmensovani pres Imagine/GD). SVG nema srcset.
  Pripona se porovnava bez ohledu na velikost pismen (`.JPG`).
- `API\getClassDescription()` (zastarala, pouziva ji `getFullDataFromClass`): trida s vazbou sama na sebe
  (napr. `crm_pages.parent_page`) nebo cyklus A -> B -> A se zanorovala do nekonecna - na webu napr. chybejici
  polozka menu typu "object". Vnoreny popis uz rozpracovane tridy je zkraceny (bez atributu).
- `BaseObject`: pri nastavenem `Configurator::$editLanguageId` a bez zadaneho jazyka konstruktor prepsal jazyk
  na null - vnoreny objekt (napr. `setValue("url.url", ...)`) pak spadl na "Column lang_id cannot be null".
  Poradi je: zadany jazyk, jazyk editace, aktualni jazyk.

Projekt: jen `composer update michalosoft/project-classes`.

## v1.4.2 - 2026-10-06

- `Users::getName()`: uzivatel bez vyplneneho jmena vraci prazdny retezec (drive TypeError - navratovy typ string, v DB NULL).

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
