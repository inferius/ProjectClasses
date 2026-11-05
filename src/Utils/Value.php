<?php

declare(strict_types=1);

namespace API\Utils;

/**
 * Prevody hodnot z DB pro vestavene funkce PHP. Knihovna ma strict_types, takze PHP uz samo neprevede
 * DECIMAL z PDO ("199.00") na cislo ani Nette DateTime na retezec - prevod musi byt vyslovny.
 */
final class Value {
    /**
     * Cislo pro number_format() / NumberFormatter: int nebo float, neciselna hodnota (null, "") = 0.
     */
    public static function toNumber(mixed $value): int|float {
        if (is_int($value) || is_float($value)) return $value;
        if (is_bool($value)) return (int)$value;
        if (is_string($value) && is_numeric(trim($value))) return trim($value) + 0;
        return 0;
    }

    /**
     * Unix timestamp z hodnoty sloupce datum/cas: DateTimeInterface, cislo nebo retezec pro strtotime().
     * Prazdna nebo necitelna hodnota = null.
     */
    public static function toTimestamp(mixed $value): ?int {
        if ($value instanceof \DateTimeInterface) return $value->getTimestamp();
        if (is_int($value)) return $value;
        if (is_string($value) && trim($value) !== "") {
            $ts = strtotime($value);
            return $ts === false ? null : $ts;
        }
        return null;
    }
}
