<?php

namespace API\Generated;

/**
 * Nahrada cache tmp/cache/methods/GeneratedMethods.php, kterou stavi administrace (MethodCacheBuilder).
 * Podpis jako ve vygenerovanem souboru, jen bez typu mixed (testy bezi i na PHP 7.4).
 */
final class GeneratedMethods {
    public static function Add($value = null, array $context = []): string {
        extract($context, EXTR_SKIP);
        return "cache:" . ($a + $b);
    }

    public static function Echo_value($value = null, array $context = []) {
        return $value;
    }
}
