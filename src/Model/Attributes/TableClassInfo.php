<?php

declare(strict_types=1);

namespace API\Model;

class TableClassInfo {
    const LANG_TABLE_PREFIX = "_lang_data";
    const LANG_TABLE_PARENT_ID_COLUMN_NAME = "parent_id";
    const LANG_TABLE_ID_COLUMN_NAME = "id";

    public function getTableName() {
        return $this->table;
    }

    public function getTableLangName(): string {
        return $this->table . self::LANG_TABLE_PREFIX;
    }

    public function getColumnNameParentId(): string {
        return self::LANG_TABLE_PARENT_ID_COLUMN_NAME;
    }

    public function getColumnNameId(): string {
        return self::LANG_TABLE_ID_COLUMN_NAME;
    }

    public function __construct(private readonly string $table)
    {
    }
}