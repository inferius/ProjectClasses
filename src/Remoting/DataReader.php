<?php

declare(strict_types=1);

namespace API;

use API\Remoting\DataReader\DataReaderItem;

class DataReader {
    private static array $supporsted_languages = [];
    private ?\API\DataReaderOrder $order = null;
    private ?\API\DataReaderWhere $condition = null;
    private $langsId;
    private $defaultLangId;
    private ?string $classId = null;
    private ?\API\Model\ClassDescription $classInfo = null;
    /** @var string[] $attributes */
    private $attributes;

    /** @var DataReaderItem[] $results */
    private $results;

    /** @var string[] $result_map */
    private ?array $result_map = null;

    public static function create(string $className, $defaultLang = null): self {
        $t = new self();
        $t->classId = $className;
        $t->classInfo = \API\Model\ClassDescription::get($className);
        $t->order = new DataReaderOrder($t->classInfo);
        $t->condition = new DataReaderWhere();

        if (!empty($defaultLang)) $t->defaultLangId = $defaultLang;

        if (empty(self::$supporsted_languages)) {
            $db = Configurator::$connection;
            $sllist = $db->fetchAll("SELECT * FROM _front_language_list");
            foreach ($sllist as $sl) {
                if ($sl["def"] && empty($defaultLang)) $t->defaultLangId = $sl["id"];
                self::$supporsted_languages[$sl["locale"]] = $sl["id"];
            }
        }

        return $t;
    }

    public function isLangaugeSupported($lang_id): bool {
        if (intval($lang_id) == $lang_id) {
            return in_array($lang_id, array_values(self::$supporsted_languages));
        } else {
            return array_key_exists($lang_id, self::$supporsted_languages);
        }
    }

    public function getResult($lang_id = null) {
        if (!empty($lang_id)) {
            if (!$this->isLangaugeSupported($lang_id)) throw new \InvalidArgumentException("Language is not supported");
            if ($this->defaultLangId == $lang_id) return $this->getResult();

            if (!array_key_exists($lang_id, $this->result_map)) {
                $this->result_map[$lang_id] = $this->readData($lang_id);
            }

            return $this->result_map[$lang_id];
        }
        else {
            return $this->results;
        }
    }

    private function readData($langId = null): void {
        if (empty($langId)) $langId = $this->defaultLangId;

        $table_name = $this->classInfo->table()->getTableName();
    }

    private static function getClassDataInner($classId, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $filterCondition = null, $lang_id = null): array {
        $class_info = \API\Model\ClassDescription::get($classId);
        $table_name = $class_info->table()->getTableName();

        $q_builder = [
            "query" => null,
            "base" => [
                "table" => $table_name,
                "columns" => ["{$table_name}.id"]
            ],
            "joins" => [],
        ];

        $tables_in_join = [];

        $addJoin = function($table, $connector, $lang_table = false) use (&$tables_in_join, &$q_builder): void {
            if (!in_array($table, $tables_in_join)) {
                $q_builder["joins"][] = [
                    "table" => $table,
                    "is_lang_table" => $lang_table,
                    "connector" => $connector
                ];
                $tables_in_join[] = $table;
            }
        };

        $processAttr = function (\API\Model\ClassDescription $attr_info, $c_attr, $attr) use (&$q_builder, $addJoin): void {
            /** @var \API\Model\ClassDescription $attr_info */
            if ($attr_info->getAttributeInfo($c_attr)->flags()->isLocalizable()) {
                $q_builder["base"]["columns"][] = "`{$attr_info->table()->getTableLangName()}`.`{$c_attr}` as `$attr`";
                $addJoin("{$attr_info->table()->getTableLangName()}_lang_data", "`{$attr_info->table()->getTableName()}`.`id` = `{$attr_info->table()->getTableLangName()}`.`parent_id`", true);
            }
            else {
                $q_builder["base"]["columns"][] = "`{$attr_info->table()->getTableName()}`.`{$c_attr}` as `$attr`";
            }
        };

        foreach ($attributes as $attr) {
            if (!str_contains($attr, ".")) {
                $processAttr($class_info, $attr, $attr);
            }
            else {
                $split_attrs = array_reverse(explode(".", $attr));
                $info = $class_info;
                $last_info = null;
                $c_attr = array_pop($split_attrs);
                while(count($split_attrs) > 0) {
                    $last_attr = $c_attr;
                    $c_attr = array_pop($split_attrs);
                    $last_info = $info;

                    if ($info->getAttributeInfo($last_attr)->flags()->isLocalizable()) {
                        throw new \InvalidArgumentException("Binding attribute don't support localization.");
                    }

                    /** @var \API\Model\ClassDescription $last_info */
                    /** @var \API\Model\ClassDescription $info */
                    $info = $info->getAttributeInfo($last_attr)->getSpecification();

                    $addJoin($info->table()->getTableName(), "`{$info->table()->getTableName()}`.`id` = `{$last_info->table()->getTableName()}`.`{$last_attr}`");
                }

                if (!array_key_exists($c_attr, $info["attributes"])) {
                    throw new \API\Exceptions\AttributeTypeNotFound($c_attr, "Attribute '$c_attr' on class '{$info["text_id"]}' not found");
                }

                $processAttr($info, $c_attr, $attr);

                //$q_builder["base"]["columns"][] = "`{$info["table"]}`.`{$c_attr}` as `$attr`";

            }
        }

        $q = "SELECT " . join(",", $q_builder["base"]["columns"]) . " FROM `$table_name`";
        foreach ($q_builder["joins"] as $join) {
            if ($join["is_lang_table"]) {
                $q .= " INNER JOIN (SELECT * FROM {$join["table"]} WHERE lang_id = 0 OR lang_id = $lang_id) as `{$join["table"]}` ON {$join["connector"]}";
            }
            else {
                $q .= " INNER JOIN `{$join["table"]}` ON {$join["connector"]}";
            }
        }


        $q .= " WHERE (`{$class_info["table"]}`.`id` != 1)";

        if (!empty($condition)) $q .= " AND ". self::updateReaderCondition($condition, $table_name);
        if (!empty($filterCondition)) $q .= " AND (" . self::updateReaderCondition($filterCondition, $table_name) . ")";

        if (!empty($order) && is_array($order)) {
            $o_q = "";
            foreach ($order as $ord) {
                if ($o_q != "") $o_q.= ", ";
                $o_attrName = $ord["attrName"];

                if (strtolower($o_attrName) == "rand()")  {
                    $o_q .= strtolower($o_attrName);
                    continue;
                }

                if (str_contains($o_attrName, ".")) {
                    $exp_attrName = explode(".", $o_attrName, 2);
                    $exist_class_order = \API\Model\ClassDescription::get($exp_attrName[0]);
                    if (!empty($exist_class_order)) {
                        $o_attrName = $exist_class_order->table()->getTableName() . "." . $exp_attrName[1];
                    }
                }

                if ($o_attrName == "id") $o_attrName = "{$table_name}.".$o_attrName;

                $o_q .= "{$o_attrName}";
                if (!empty($ord["direction"])) $o_q .= " {$ord["direction"]}";
            }

            if (!empty($o_q)) $q .= " ORDER BY {$o_q}";
        }

        $offset = $page * $limit;
        $q.= " LIMIT {$limit} OFFSET $offset";

        $q_builder["query"] = $q;

        return $q_builder;
    }



    public function getClassData($classId, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $filterCondition = null, $filterParams = [], $lang_id = null): array {
        $connection = Configurator::$connection;

        $prepared = self::getClassDataInner($classId, $attributes, $page, $limit, $order, $condition, $filterCondition, $lang_id);
        if (empty($filterCondition)) {
            $full_data_sql = $connection->query($prepared["query"], ...$filterParams);
        }
        else {
            $full_data_sql = $connection->query($prepared["query"]);
        }

        $r_value = [];
        $f_ids = [];

        $file_attrs = [];
        $file_map = [];

        foreach ($attributes as $attr) {
            $attr_info = getAttributeInfo($classId, $attr);
            if ($attr_info["type"] == "file") $file_attrs[] = $attr;
        }

        foreach ($full_data_sql as $item) {
            $r_row_value = [];
            foreach ($attributes as $attr) {
                $attr_info = getAttributeInfo($classId, $attr);
                $new_val = [
                    "raw" => $item[$attr],
                    "value" => $item[$attr]
                ];

                if ($attr_info["type"] == "file") {
                    $f_ids[] = $item[$attr];
                }
                else if ($attr_info["type"] == "date") {
                    $new_val["value"] = empty($item[$attr]) ? $item[$attr] : $item[$attr]->format("j. n. Y");
                }

                $r_row_value[$attr] = $new_val;
            }
            $r_value[] = $r_row_value;
        }

        if (!empty($f_ids)) {
            $files = $connection->query("SELECT * FROM fp_temp_files WHERE id in (?)", $f_ids);

            foreach ($files as $file) {
                $file_map[$file["id"]] = $file;
            }

            $i = 0;
            foreach ($r_value as $rv) {
                foreach ($file_attrs as $attr) {
                    if (empty($rv[$attr]["value"])) continue;
                    $r_value[$i][$attr]["value"] = $file_map[$rv[$attr]["value"]];
                }
                $i++;
            }
        }

        return $r_value;


    }

    public static function getTableData($table_name, $join_definition, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $lang_id = null): array {
        $q_builder = [
            "query" => null,
            "base" => [
                "table" => $table_name,
                "columns" => ["{$table_name}.id"]
            ],
            "joins" => [],
        ];

        $tables_in_join = [];
        $prepared_joins = [];

        $q = "";

        $addJoin = function($table, $table_index, $connector) use (&$tables_in_join, &$q_builder): void {
            if (!in_array($table_index, $tables_in_join)) {
                $q_builder["joins"][] = [
                    "table" => $table,
                    "connector" => $connector
                ];
                $tables_in_join[] = $table_index;
            }
        };

        $processAttr = function ($table, $c_attr, $attr) use (&$q_builder, $addJoin): void {
            $q_builder["base"]["columns"][] = "`{$table}`.`{$c_attr}` as `$attr`";
        };



        if (!empty($join_definition)) {
            foreach ($join_definition as $jd) {
                $join_table = $jd["tableName"];
                $root_table = empty($jd["rootTable"]) ? $table_name : $jd["rootTable"];
                $table_index = $join_table . "|" . $root_table;
                $table_swap_index = $root_table . "|" . $join_table;
                $join_attr = $jd["sourceAttr"];
                $root_attr = empty($jd["rootTableAttr"]) ? "id" : $jd["rootTableAttr"];

                if (in_array($table_index, $prepared_joins) || in_array($table_swap_index, $prepared_joins)) continue;
                $prepared_joins[] = $table_index;

                $addJoin($join_table, $table_index, "`{$join_table}`.`{$join_attr}` = `{$root_table}`.`{$root_attr}`");
            }
        }

        foreach ($attributes as $attr) {
            if (!str_contains($attr, ".")) {
                $processAttr($table_name, $attr, $attr);
            }
            else {
                $split_attrs = array_reverse(explode(".", $attr));
                $c_attr = array_pop($split_attrs);

                while(count($split_attrs) > 0) {
                    $last_attr = $c_attr;
                    $c_attr = array_pop($split_attrs);

                    $join_index = $c_attr . "|" . $table_name;
                    if (in_array($join_index, $prepared_joins)) throw new \InvalidArgumentException("Join not found in definition");
                }

                $processAttr($last_attr, $c_attr, $attr);

                //$q_builder["base"]["columns"][] = "`{$info["table"]}`.`{$c_attr}` as `$attr`";

            }
        }


        $q = "SELECT " . join(",", $q_builder["base"]["columns"]) . " FROM `$table_name`";
        foreach ($q_builder["joins"] as $join) {
            $q .= " INNER JOIN `{$join["table"]}` ON {$join["connector"]}";
        }

        $q_w = "";

        if (!empty($condition)) {
            if (!empty($q_w)) $q_w .= " AND ";
            $q_w .= self::updateReaderCondition($condition, $table_name);

            $q .= " WHERE " . $q_w;
        }

        if (!empty($order) && is_array($order)) {
            $o_q = "";
            foreach ($order as $ord) {
                if ($o_q != "") $o_q.= ", ";
                $o_attrName = $ord["attrName"];
                if ($o_attrName == "id") $o_attrName = "{$table_name}.".$o_attrName;
                $o_q .= "{$o_attrName}";
                if (!empty($ord["direction"])) $o_q .= " {$ord["direction"]}";
            }

            if (!empty($o_q)) $q .= " ORDER BY {$o_q}";
        }

        if ($limit >= 0) {
            $offset = $page * $limit;
            $q.= " LIMIT {$limit} OFFSET $offset";
        }
        //var_dump($q);

        $q_builder["query"] = $q;

        return $q_builder;
    }

    private static function updateReaderCondition($cond, $table_name): string|array|null {

        $cond = preg_replace_callback("/([A-Za-z._]+)(?=[ ]*(?:[<>=]|LIKE))/", function($matches) use ($table_name): string {
            if ($matches[0] == "id") return "{$table_name}.{$matches[0]}";

            return $matches[0];
        }, $cond);

        return $cond;
    }
}