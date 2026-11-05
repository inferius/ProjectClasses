<?php

declare(strict_types=1);

namespace API\Remoting\DataReader;

class DataReaderItem {
    private $results;

    public function __construct(private $defLangId = null)
    {
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