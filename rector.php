<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// vendor/bin/rector process --dry-run (vyvoj knihovny; zmeny se kontroluji rucne - kontrakty v UPGRADE-2.0.md)
return RectorConfig::configure()
    ->withPaths([ __DIR__ . "/src", __DIR__ . "/tests" ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(typeDeclarations: true)
    ->withAttributesSets(phpunit: true)
    ->withComposerBased(phpunit: true);
