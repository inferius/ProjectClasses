<?php

namespace API;

class TableObject {

    private $id = null;
    private $columnInfo = [];
    private $tableName;

    private $tableInfo = [];

    /** @var TableAttributeValue[] */
    private $values = [];

    private $isEdited = false;

    public function getId() { return $this->id; }
    public function getTableName() { return $this->tableName; }

    public function __construct($tableName, $id = null) {
        $this->tableName = $tableName;
        $this->id = $id;

        $this->_checkTableExist();
        $this->_loadTableInfo();

        $this->_reload();
    }

    protected function _reload() {

        foreach ($this->columnInfo as $key => $val) {
            $this->values[$key] = new TableAttributeValue($key, null, $val["datatype"]);
            $this->values[$key]->isEdited = $this->id == null;
        }

        if ($this->id != null) {
            $exist = \API\Configurator::$connection->fetch("SELECT * FROM `{$this->tableName}` WHERE id = ?", $this->id);
            if (empty($exist)) throw new \InvalidArgumentException("Row with id '{$this->id}' on table '{$this->tableName}' not found");

            foreach ($exist as $key => $val) {
                $this->values[$key]->setValue($val);
                $this->values[$key]->isEdited = false;
            }
        }

    }

    protected function _checkTableExist() {
        global $connectionInformationSchema;
        global $config;

        $exist = $connectionInformationSchema()->fetch("SELECT * FROM `TABLES` WHERE table_name = ? AND TABLE_SCHEMA = ?", $this->tableName, $config["database"]["db_name"]);
        

        if (empty($exist)) throw new \InvalidArgumentException("Table '{$this->tableName}' not found");

        foreach ($exist as $key => $val) {
            $this->tableInfo[$key] = $val;
            $this->tableInfo[strtolower($key)] = $val;
        }

    }

    protected function _loadTableInfo() {
        global $connectionInformationSchema;
        global $config;
        global $memcache;

        
        if (class_exists("Memcache")) {
            $this->columnInfo = $memcache->get("TableObjectColumnInfo:" . $this->tableName);
        }
        

        if (empty($this->columnInfo)) {
            try {
                $column_infos = $connectionInformationSchema()->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = ? AND table_schema = ?", $this->tableName, $config["database"]["db_name"]);
                foreach ($column_infos as $columnInfo) {
                    $this->columnInfo[strtolower($columnInfo["COLUMN_NAME"])] = [
                        "name" => $columnInfo["COLUMN_NAME"],
                        "datatype" => $columnInfo["DATA_TYPE"],
                        "canBeNull" => $columnInfo["IS_NULLABLE"] == "YES",
                        "textMaxLength" => $columnInfo["CHARACTER_MAXIMUM_LENGTH"],
                        "isPrimary" => $columnInfo["COLUMN_KEY"] == "PRI"
                    ];
                }

                if (class_exists("Memcache")) {
                    $column_infos = $memcache->set("TableObjectColumnInfo:" . $this->tableName, $this->columnInfo);
                }

            }
            catch (\Exception $e) {
            }
        }
        
    }

    public function save() {
        
        if (!$this->isEdited) return;

        $saveAttrs = [];

        foreach ($this->values as $key => $val) {
            if ($val->isEdited) {
                $saveVal = $val->save();
                $this->_checkAttr($key, $saveVal);
                $saveAttrs[$key] = $saveVal;
            }
        }

        if ($this->id == null) {
            \API\Configurator::$connection->query("INSERT INTO `{$this->tableName}` ", $saveAttrs);
            $this->id = \API\Configurator::$connection->getInsertId();
        }
        else {
            \API\Configurator::$connection->query("UPDATE `{$this->tableName}` SET ", $saveAttrs, " WHERE id = ?", $this->id);
        }
    }

    private function _checkAttr(&$attrName, $value) {
        $attrName = strtolower($attrName);
        if (empty($this->columnInfo[$attrName])) {
            throw new \InvalidArgumentException("Attribute '$attrName' on table '{$this->tableName}' not exist.");
        }

        $attrInfo = $this->columnInfo[$attrName];

        if ($value === null && !$attrInfo["canBeNull"] && !$attrInfo["isPrimary"]) {
            throw new \InvalidArgumentException("Attribute '$attrName' can't be null.");
        }

        if (!empty($attrInfo["textMaxLength"])) {
            if (mb_strlen($value, "UTF-8") > $attrInfo["textMaxLength"]) {
                trigger_error("Value is too long for attribute '{$attrName}'. It has max.length is '{$attrInfo["textMaxLength"]}' chars.", E_USER_WARNING);
            }
        }
    }

    public function delete() {
        
        if ($this->id == null) return;
        \API\Configurator::$connection->query("DELETE FROM `{$this->tableName}` WHERE id = ?", $this->id);
    }

    public function getItem($attrName): ?TableAttributeValue {
        $attrName = strtolower($attrName);
        if (empty($this->columnInfo[$attrName])) {
            throw new \InvalidArgumentException("Attribute '$attrName' on table '{$this->tableName}' not exist.");
        }

        return $this->values[$attrName];
    }

    public function getValue($attrName) {
        return $this->getItem($attrName)->getValue();
    }

    public function setValue($attrName, $value) {
        $this->_checkAttr($attrName, $value);

        $this->isEdited = true;

        $this->values[$attrName]->setValue($value);
    }
}

