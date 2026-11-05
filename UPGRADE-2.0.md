# Prechod na project-classes 2.0

Verze 2.0 je zatim ve vyvoji. Navrh a rozhodnuti (2026-11-05): Claude Docs "project-classes 2.0: analyza a navrh".
Tenhle soubor rika projektum, co se zmeni a co udelat. Doplnuje se s kazdou zmenou 2.x.

## Co 2.0 prinese

- **PHP 8.3** a novejsi (`composer.json`: `php >=8.3`).
- `declare(strict_types=1)` a typy parametru, navratovych hodnot a vlastnosti v cele knihovne.
- **Opravneni v knihovne**: pravidla, resolver a kontrola pri cteni a zapisu v `BaseObject` a `DataReader`.
  Kontroluje se jen tam, kde projekt resolver nastavi - bez nej se chovani nemeni.
- `DataReader` jako jedine rozhrani pro seznamy (prevezme `getDataFromClass()` a `getFullDataFromClass()`
  z frameworku; funkce frameworku zustanou jako obaly se stejnymi signaturami).
- Uzivatel v session jen jako id a token (ne cely objekt `Users`).
- Klice memcache s verzi knihovny.
- `API\Frontend\Head` - titulek, meta, canonical, hreflang a JSON-LD do `<head>` z sablon i modulu
  (kostra webu vypise `{\API\Frontend\Head::render()|noescape}` v hlavicce).

## Co se zmenilo v chovani

| Co | Ve 2.0 | Drive |
| --- | --- | --- |
| `new BaseObject($trida, $id)` | id `int`, ciselny retezec nebo prazdna hodnota; neciselny retezec = `ObjectNotFoundException` | retezec se poslal do SQL, objekt byl prazdny |
| `BaseObject::getId()` | `?int`, u noveho objektu null | `int`, u noveho objektu TypeError |
| `BaseObject::getCreated()` | `DateTime` ze sloupce `created` | `false` (spatny format) |
| `Localization::getText()` | `?string`, chybejici preklad null | null nebo false (memcache) |
| klice memcache popisu trid | `pc2:[prefix:]ModelClassDescription...` | bez verze |

## Co se odstrani (v 1.8 hlasi E_USER_DEPRECATED)

| Odstraneno | Nahrada |
| --- | --- |
| `PlainUrl::redirectWithoutSlash()` | `PlainUrl::redirectAddSlash()` |
| `PlainUrlToken::slash_at_end()` | `PlainUrlToken::slashAtEnd()` |
| `PageTemplate::load_css_js()` | `PageTemplate::loadCssJs()` |
| `DataReader::getClassDataInner2()` | `DataReader::getClassData()` |
| `API\Frontend\DataReader`, `API\Model\DataLoader`, `API\DateTimeConfigurator`, `API\FunctionLine` | nic (prazdne nebo nedokoncene tridy) |

`API\ClassDescription::get()` (popis tridy jako pole) zustava oznacena jako zastarala az do 3.0.

## Co zustane beze zmeny

- Nazvy trid `API\...`. Globalni `Globals`, `Localization`, `L`, `ArrayTokenScanner` a `UploadException` se presunou
  do `API\`, stare nazvy zustanou jako alias.
- Promenne v `page.php` sablon (`$template_params` a promenne z `addGlobalVar`), `PageTemplate::$print_end_script`.
- `Globals::get()` vraci referenci (`Globals::get("head_data")[] = ...`).
- Konstanty `DataTypes` jako retezce, verejna `ValidationException::$custom_data`, `L::t($key, $def)` s polem.

## Co zkontrolovat v projektu

1. **PHP 8.3** na hostingu administrace i webu.
2. **Id jako retezec** z URL nebo JSON do `new BaseObject(...)`, `new Users(...)`: ciselny retezec projde, prazdny nebo
   neciselny skonci `ObjectNotFoundException`.
3. **`Configurator`**: `$connectionInformationSchema` a `$replaceTextFnc` jako Closure, `$currentLanguageId` nesmi byt null.
4. **Chybna volani**, ktera 2.0 odmitne: `Users::createUser()` se 3 argumenty (`webapi/global/register.php`).
5. Po nasazeni: vymazat memcache a pocitat s jednorazovym odhlasenim prihlasenych uzivatelu.

Pomucky: `rector-2.0.php` (prepise zastarala volani) a PHPStan nad projektem proti 2.0 - pribudou s 2.0.0-beta.
