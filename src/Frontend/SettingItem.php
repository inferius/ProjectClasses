<?php

namespace API\Frontend;

class SettingItem {
    private $name;
    private $attrname;
    private $type;
    private $value;
    private $num_type;

    private static $input_type = [ "number", "number", "text", "textarea", "datetime", "textarea", "checkbox" ];

    public function __construct($type, $value, $attrname, $name, $num_type) {
        $this->type = $type;
        $this->value = $value;
        $this->attrname = $attrname;
        $this->name = $name;
        $this->num_type = $num_type;
    }

    /** @return string  */
    public function getAttrName() { return $this->attrname; }
    /** @return string  */
    public function getInputType() { return self::$input_type[$this->num_type];}
    /** @return string  */
    public function getName() { return $this->name; }
    /** @return string  */
    public function getType() { return $this->type; }
    /** @return mixed */
    public function getValue() { return $this->value; }

    /**
     * @param mixed $value Hodnota atributu
     * @return void
     */
    public function setValue($value) {

        $res = \API\Configurator::$connection->query("UPDATE frontend_setting SET ", [ $this->type => $value ], " WHERE text_id = ?", $this->attrname);
        $affected_rows = $res->getRowCount();

        if ($affected_rows > 0) $this->value = $value;
    }

    /**
     * Vrátí hodnotu jako integer je použita funkce intval
     * @return int
     */
    public function toInt(): int {
        return intval($this->value);
    }

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
     *  Number[integer,decimal]
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
     * @return false|float|int|string
     */
    public function toFormat($format = null, ...$args) {
        if ($this->type == "datetime_val") {
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

                    return $formatter->getPattern();
                }
                else {
                    return date("j. n. Y H:i:s", strtotime($this->value));
                }
            }
        }
        else if ($this->type == "decimal_val" || $this->type == "int_val") {
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
        }

        return $this->value;
    }
}