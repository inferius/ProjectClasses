<?php

namespace API;

class FunctionLine {
    private static $allowed_functions = [ "resize", "crop", "use", "mask", "watermark" ];

    function __construct($line) {

    }
}

// trida se nikde nepouziva a ve 2.0 nebude - varovani pri nacteni souboru (autoload)
\API\Utils\Deprecation::trigger("API\FunctionLine (nedokoncena trida)");
