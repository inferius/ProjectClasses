<?php

namespace API\Model;

class FlagsAttributeInfo {
    private $is_required = false;
    private $is_public = false;
    private $is_unique = false;
    private $is_localizable = false;
    private $is_versioned = false;
    private $is_filterable = false;

    public function isRequired(): bool { return $this->is_required; }
    public function isPublic(): bool { return $this->is_public; }
    public function isUnique(): bool { return $this->is_unique; }
    public function isLocalizable(): bool { return $this->is_localizable; }
    public function isVersioned(): bool { return $this->is_versioned; }
    public function isFilterable(): bool { return $this->is_filterable; }

    public function __construct($row_data) {
        $this->is_required = boolval($row_data["is_required"]);
        $this->is_public = boolval($row_data["is_public"]);
        $this->is_unique = boolval($row_data["is_unique"]);
        $this->is_localizable = boolval($row_data["is_localizable"]);
        $this->is_versioned = boolval($row_data["is_versioned"]);
        $this->is_filterable = boolval($row_data["is_filterable"]);
    }
}