<?php

declare(strict_types=1);

/**
 * Stare nazvy trid z globalniho prostoru (do 1.x). Ve 2.0 jsou tridy v API\ a stare nazvy funguji dal jako alias,
 * ktery vznikne az pri prvnim pouziti (composer "files" - nacita se s autoloaderem).
 * Odstraneni aliasu nejdriv ve 3.0.
 */
spl_autoload_register(static function (string $class): void {
    static $map = [
        "globals" => \API\Globals::class,
        "localization" => \API\Localization::class,
        "l" => \API\L::class,
        "arraytokenscanner" => \API\ArrayTokenScanner::class,
        "uploadexception" => \API\Exceptions\UploadException::class,
    ];

    $key = strtolower(ltrim($class, "\\"));
    if (isset($map[$key])) class_alias($map[$key], $class);
});
