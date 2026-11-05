<?php

declare(strict_types=1);

namespace API;

/**
 * Globalni nastaveni knihovny - projekt ho naplni pri startu (init.inc.php).
 *
 * Vlastnosti, ktere projekt plni objektem tretich stran (spojeni s DB, memcache, presenter, funkce), zustavaji
 * bez nativniho typu - typ je v @var. Testy je nahrazuji vlastnimi objekty (tests/Support).
 */
class Configurator {
    /** @var \Nette\Database\Explorer|null */
    public static $explorer;
    /** @var \Nette\Database\Connection|null */
    public static $connection;
    /**
     * Spojeni do information_schema (TableObject) - projekt dava funkci, ktera spojeni vytvori az pri prvnim pouziti.
     * @var \Closure|\Nette\Database\Connection|null
     */
    public static $connectionInformationSchema;
    /** @var \Memcache|\Memcached|null */
    public static $memcache;

    /**
     * Aktualni locale (formatovani data a cisel, klice prekladu). Nullable: weby ho plni ze session, kde muze chybet;
     * knihovna cte pres locale() s vychozim DEFAULT_LOCALE.
     */
    public static ?string $locale = self::DEFAULT_LOCALE;

    public const string DEFAULT_LOCALE = "cs_CZ";

    /** Locale pro formatovani a klice prekladu - $locale, bez nej DEFAULT_LOCALE */
    public static function locale(): string {
        return self::$locale === null || self::$locale === "" ? self::DEFAULT_LOCALE : self::$locale;
    }

    /**
     * ID aktualniho jazyka (web: ze session). Null = vychozi jazyk 2 (BaseObject).
     * Projekt bez strict_types muze priradit i ciselny retezec, PHP ho prevede.
     */
    public static ?int $currentLanguageId = 2;

    /** ID jazyka editace v administraci; ma prednost pred $currentLanguageId */
    public static ?int $editLanguageId = null;

    /** Prefix klicu prekladu v memcache, pokud vic webu sdili jednu memcache */
    public static string $localizationPrefix = "loc";

    /**
     * Prefix klicu memcache pro popisy trid a vyctu (ModelClassDescription..., ModelEnumDescription...).
     * Nastavit, kdyz vic projektu sdili jednu memcache - jinak si stejne pojmenovane tridy prepisuji popis.
     * Administrace zneplatnuje pres ClassDescription::forget(), ne skladanim klice.
     */
    public static string $cachePrefix = "";

    /**
     * Verze klicu cache (popisy trid a vyctu se ukladaji serializovane - objekty z 1.x do typovanych trid 2.0
     * rozbalit nejdou, proto maji 2.0 klice vlastni).
     */
    public const string CACHE_VERSION = "pc2";

    /**
     * Klic memcache s verzi knihovny a prefixem projektu ($cachePrefix)
     */
    public static function cacheKey(string $key): string {
        return self::CACHE_VERSION . ":" . (self::$cachePrefix === "" ? "" : self::$cachePrefix . ":") . $key;
    }

    /** Konfiguracni pole projektu (config.inc.php) */
    public static array $config = [];

    /**
     * Prezentacni vrstva (Latte\Engine nebo objekt s render() a renderToString())
     * @var object|null
     */
    public static $presenter;

    /**
     * Funkce pro nahrazovani textu (Functions\Argument)
     * @var callable|null
     */
    public static $replaceTextFnc;
}
