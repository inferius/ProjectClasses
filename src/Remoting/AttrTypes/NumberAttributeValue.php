<?php

namespace API;

use API\Model\IAttributeInfo;

class NumberAttributeValue extends AttributeValue {

    /**
     * Vrátí hodnotu za použítí formátování podle typu
     *
     * @param string|null $format Použítý formát liší se na základě typu
     *      - Pokud je vyplněný formát a zároveň podporuje třídu \NumberFormatter, je použita pro formátování
     *        - Pak se do formátu předavávají \NumberFormatter::DECIMAL, atd. Dodatečné parametry se předávají jako nepovinné argumenty v pořadí
     *          $attributes = [ \NumberFormatter::MAX_FRACTION_DIGITS => 2, ... ],
     *          $pattern = "#0.# kg",
     *          $symbols = [ \NumberFormatter::GROUPING_SEPARATOR_SYMBOL => "*" ],
     *          $textAttributes = [\NumberFormatter::NEGATIVE_PREFIX => "MINUS"], za rovná se jsou uvedeny ukazkové hodnoty
     *
     *          Výchozí je použít \NumberFormatter::DECIMAL, pokud jej chcete použít stačí jako argument použít string
     *      - Jinak je použita klasická funkce number formát, kterou lze ovlivnit předanýma dodatečnýma parametrama v pořadi `$decimal_count = 0; $decimal_separator = ','; $thousands_separator = ' '`, kde za rovna se je uvedena výchozí hodnota
     *
     * @return string
     */
    public function toFormat($format = null, ...$args) {
        if ((is_string($format) || is_int($format)) && class_exists("NumberFormatter")) {
            if (is_string($format)) $style = \NumberFormatter::DECIMAL;
            else {
                $style = $format;
            }

            @list($attributes, $pattern, $symbols, $textAttributes) = $args + [ null, null, null, null];

            $f = new \NumberFormatter(\API\Configurator::$locale, $style);

            if (is_array($attributes)) {
                foreach ($attributes as $attrName => $val) $f->setAttribute($attrName, $val);
            }

            if (!empty($pattern)) $f->setPattern($pattern);

            if (is_array($symbols)) {
                foreach ($symbols as $symbol => $val) $f->setSymbol($symbol, $val);
            }

            if (is_array($textAttributes)) {
                foreach ($textAttributes as $attrName => $val) $f->setTextAttribute($attrName, $val);
            }

            return $f->format($this->value);
        }
        else {
            list($decimal_count, $decimal_separator, $thousands_separator) = $args + [0, ',', ' '];

            return number_format($this->value, $decimal_count, $decimal_separator, $thousands_separator);
        }

        return $this->value;
    }
}