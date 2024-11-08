<?php

namespace API;

use API\Model\IAttributeInfo;

class DateTimeAttributeValue extends AttributeValue {

    /**
     * Vrátí hodnotu za použítí formátování podle typu
     *
     * @param string|null $format Použítý formát liší se na základě typu
     *
     *  Typ:
     *  DateTime
     *      - "sql" : Vrátí hodnotu přímo z databáze
     *      - "timestamp" : Vrátí PHP timestamp
     *      - "js_timestamp" : Vrátí timestamp kompatibilní s JavaScriptem
     *      - null : Pokud je dostupná třída \IntlDateFormatter vrátí formátování na základě aktuální lokalizace v \API\Configurator::$locale
     *         - podporuje dodatečné argumenty, kdyz prvni argument se stara o nastaveni formátu data a druhý o nastavení formátu času, jako výchozí je použit \IntlDateFormatter::SHORT
     *         - Pokud není dostupná výše zmíněna třída je vráceno ve formátu j. n. Y H:i:s
     *      - string : Pokud je použit string, který neodpovídá ničemu z výše uvedeného je použít pro určení formátu funkce `date`
     *
     * @return string
     */
    public function toFormat(...$args) {
        $def = [ null ];
        @list($format) = $args + $def;
        if (is_string($format)) {
            switch (strtolower($format)) {
                case "sql":
                    return $this->value;
                case "timestamp":
                    return strtotime($this->value);
                case "js_timestamp":
                    return strtotime($this->value) * 1000;
                default:
                    return date($format, strtotime($this->value));
            }
        }
        else {
            if (class_exists("IntlDateFormatter")) {
                @list($dateFormatter, $timeFormatter) = $args + [ \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT ];

                $formatter = new \IntlDateFormatter(\API\Configurator::$locale, $dateFormatter, $timeFormatter);
                if ($formatter === null)
                    throw new InvalidConfigException(intl_get_error_message());

                return $formatter->format($this->value);
            }
            else {
                return date("j. n. Y H:i:s", strtotime($this->value));
            }
        }
    }
}
