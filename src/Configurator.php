<?php

namespace API;

use phpDocumentor\Reflection\Types\Callable_;

class Configurator {
    /** @var \Nette\Database\Explorer $explorer */
    public static $explorer;
    /** @var \Nette\Database\Connection $connection */
    public static $connection;
    /** @var Callable_\Nette\Database\Connection $connectionInformationSchema */
    public static $connectionInformationSchema;
    /** @var \Memcache $memcache */
    public static $memcache;
    /** @var string $locale Obsahuje aktuální jazyk, který je použit pro předání */
    public static $locale = "cs_CZ";

    /**
     * ID aktuálního jazyka, načtě se ze session na webu
     * @var int
     */
    public static $currentLanguageId = 2;

    /**
     * ID aktuálního jazyka, načtě se ze session na webu
     * @var int
     */
    public static $editLanguageId = null;

    /**
     * Prefix lokalizací, pokud se na stejném serveru nacházi více webu, aby se nehádali
     * @var string
     */
    public static $localizationPrefix = "loc";

    /**
     * Prefix klicu memcache pro popisy trid a vyctu (ModelClassDescription..., ModelEnumDescription...).
     * Prazdny = klice jako do 1.7. Nastavit, kdyz vic projektu sdili jednu memcache - jinak si stejne pojmenovane
     * tridy prepisuji popis. Administrace pak musi zneplatnovat pres ClassDescription::forget(), ne skladat klic sama.
     * @var string
     */
    public static $cachePrefix = "";

    /**
     * Klic memcache s prefixem projektu ($cachePrefix)
     * @param string $key
     * @return string
     */
    public static function cacheKey(string $key): string {
        return empty(self::$cachePrefix) ? $key : self::$cachePrefix . ":" . $key;
    }

    /** @var array Konfigurační pole */
    public static $config = [];

    /**
     * Prezentační vrstva
     * @var mixed $presenter
     */
    public static $presenter;

    /**
     * @var callable Funkce pro nahrazování textu
     */
    public static $replaceTextFnc;
}
