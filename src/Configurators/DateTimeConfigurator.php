<?php

namespace API;

class DateTimeConfigurator {
    public static $s;
}

// trida se nikde nepouziva a ve 2.0 nebude - varovani pri nacteni souboru (autoload)
\API\Utils\Deprecation::trigger("API\DateTimeConfigurator (prazdna trida)");
