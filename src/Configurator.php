<?php

namespace API;

class Configurator {
    /** @var Nette\Database\Context $explorer */
    public static $explorer;
    /** @var Nette\Database\Connection $connection */
    public static $connection;
    /** @var \Memcache $memcache */
    public static $memcache;
    /** @var string $locale Obsahuje aktuální jazyk, který je použit pro předání */
    public static $locale = "cs_CZ";

    /**
     * ID aktuálního jazyka, načtě se ze session na webu
     * @var int
     */
    public static $currentLangugageId = 2;

    /**
     * Prefix lokalizací, pokud se na stejném serveru nacházi více webu, aby se nehádali
     * @var string
     */
    public static $localizationPrefix = "loc";
}