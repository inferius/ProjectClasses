<?php

namespace API\Remoting\DataReader;

class DataReaderItem {
    private $defLangId;

    private $results;

    public function __construct($defLangId = null) {
        $this->defLangId = $defLangId;
    }

    public function getValue(string $attrName, $langId = null) {
        if (empty($langId)) $langId = $this->defLangId;
        return $this->results[$langId];
    }

    public function getRawValue(string $attrName, $langId = null) {
        if (empty($langId)) $langId = $this->defLangId;
        return $this->results[$langId];
    }
}