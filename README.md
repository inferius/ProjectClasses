# ProjectClasses
 
## Patch notes

Vsechny zmeny balicku jsou v [PATCHNOTES.md](PATCHNOTES.md) - kazda zmena se zapisuje tam (nova verze nahore).

Prechod na 2.0 (PHP 8.3, typy, opravneni): [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Testy

`composer install`, pak `vendor/bin/phpunit` (testy bez databaze), `vendor/bin/phpstan analyse` a `vendor/bin/rector process --dry-run`.
Verze 2.x potrebuje PHP 8.3+, opravy 1.x (PHP 7.4+) jsou ve vetvi `1.x`.
