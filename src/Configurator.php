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
