# ProjectClasses
 
## Release Notes

### v1.2.0
- `PlainUrl::redirectAddSlash()` (puvodni `redirectWithoutSlash()` zustava jako deprecated alias), nove `redirectRemoveSlash()`
- `Globals::get()` vraci referenci, nove `get_all()`, `clear()`, `is_set()`
- `ClassDescription::hasAttribute()`
- PHP 8.3 kompatibilita: deklarovane vlastnosti v `Users`, opraven `DataReader` (staticky pristup k instancni vlastnosti), chybejici `InvalidConfigException`, `FunctionCore::getMimeTypeByPath()` fallback na `mime_content_type`, type hinty `\Exception` ve vyjimkach
- Podporovane PHP: 7.2 - 8.3

### v1.2.1
- `PageTemplate`: CSS/JS sablon s `?v=<cas zmeny>` (`PageTemplate::assetVersion()`), spojene soubory v `CSS_JS_Cache` maji v nazvu i cas zmeny souboru - po nasazeni se nenacitaji stare verze z cache
