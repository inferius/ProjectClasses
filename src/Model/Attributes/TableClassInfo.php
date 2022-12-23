<?php

namespace API\Model;

class TableClassInfo {
    const LANG_TABLE_PREFIX = "_lang_data";
    const LANG_TABLE_PARENT_ID_COLUMN_NAME = "parent_id";
    const LANG_TABLE_ID_COLUMN_NAME = "id";

    private $table;

    public function getTableName() {
        return $this->table;
    }

    public function getTableLangName() {
        return $this->table . self::LANG_TABLE_PREFIX;
    }

    public function getColumnNameParentId() {
        return self::LANG_TABLE_PARENT_ID_COLUMN_NAME;
    }

    public function getColumnNameId() {
        return self::LANG_TABLE_ID_COLUMN_NAME;
    }

    public function __construct(string $tableName) {
        $this->table = $tableName;
    }
}