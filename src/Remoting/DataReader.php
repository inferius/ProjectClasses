<?php

namespace API;

use API\Remoting\DataReader\DataReaderItem;

class DataReader {
    private static $supporsted_languages = [];
    /** @var DataReaderOrder $order */
    private $order;
    /** @var DataReaderWhere $condition */
    private $condition;
    private $langsId;
    private $defaultLangId;
    /** @var string $classId */
    private $classId;
    /** @var \API\Model\ClassDescription $classInfo */
    private $classInfo;
    /** @var string[] $attributes */
    private $attributes;

    /** @var DataReaderItem[] $results */
    private $results;

    /** @var string[] $result_map */
    private $result_map;

    public static function create(string $className, $defaultLang = null) {
        $t = new self();
        $t->classId = $className;
        $t->classInfo = \API\Model\ClassDescription::get($className);
        $t->order = new DataReaderOrder($t->classInfo);
        $t->condition = new DataReaderWhere();

        if (empty($defaultLang)) $t->defaultLangId  = $defaultLang;

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
            if (self::$defaultLangId == $lang_id) return $this->getResult();

            if (!array_key_exists($lang_id, $this->result_map)) {
                $this->result_map[$lang_id] = $this->readData($lang_id);
            }

            return $this->result_map[$lang_id];
        }
        else {
            return $this->results;
        }
    }

    private function readData($langId = null) {
        if (!empty($langId)) $langId = self::$defaultLangId;

        $table_name = $this->classInfo->table()->getTableName();
    }

    private static function getClassDataInner($classId, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $filterCondition = null, $lang_id = null) {
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

        $addJoin = function($table, $connector, $lang_table = false) use (&$tables_in_join, &$q_builder) {
            if (!in_array($table, $tables_in_join)) {
                $q_builder["joins"][] = [
                    "table" => $table,
                    "is_lang_table" => $lang_table,
                    "connector" => $connector
                ];
                $tables_in_join[] = $table;
            }
        };

        $processAttr = function ($attr_info, $c_attr, $attr) use (&$q_builder, $addJoin) {
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
            if (strpos($attr, ".") === false) {
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

                if (strpos($o_attrName, ".") !== false) {
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


    function getClassDataInner2($classId, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $filterCondition = null, $lang_id = null) {
        $class_info = \API\Model\ClassDescription::get($classId);
        $table_name = $class_info->table()->getTableName();

        if (empty($lang_id)) {
            if (empty($_SESSION[SESS_EDIT_LANG])) $lang_id = 2;
            else {
                $lang_id = $_SESSION[SESS_EDIT_LANG]["id"];
            }
        }

        $def_lang = $_SESSION[SESS_DEF_LANG]["id"];
        //$sql_fnc = $def_lang > $lang_id ? "MIN" : "MAX";

        $q_builder = [
            "query" => null,
            "has_localization" => false,
            "base" => [
                "table" => $table_name,
                "columns" => ["{$table_name}.id"]
            ],
            "joins" => [],
        ];

        $tables_in_join = [];

        $addJoin = function($table, $connector, $lang_table = false, $connector_l2 = null, $table_l2 = null) use (&$tables_in_join, &$q_builder) {
            if (!in_array($table, $tables_in_join)) {
                $q_builder["joins"][] = [
                    "table" => $table,
                    "table_l2" => $table_l2,
                    "is_lang_table" => $lang_table,
                    "connector" => $connector,
                    "connector_l2" => $connector_l2
                ];
                $tables_in_join[] = $table;
            }
        };

        $processAttr = function ($table, $attr_info, $c_attr, $attr) use (&$q_builder, $addJoin, $lang_id, $def_lang) {

            if ($attr_info["attributes"][$c_attr]["description"]["is_localizable"] == 1) {
                if ($def_lang != $lang_id) {
                    $q_builder["base"]["columns"][] = "coalesce(`{$table}_lang_data`.`{$c_attr}`, `{$table}_lang_data_l2`.`{$c_attr}`) as `$attr`";
                }
                else {
                    $q_builder["base"]["columns"][] = "`{$table}_lang_data`.`{$c_attr}` as `$attr`";
                }
                $addJoin("{$table}_lang_data", "`{$table}`.`id` = `{$table}_lang_data`.`parent_id`", true, "`{$table}`.`id` = `{$table}_lang_data_l2`.`parent_id`", "{$table}_lang_data_l2");
            }
            else {
                $q_builder["base"]["columns"][] = "`{$table}`.`{$c_attr}` as `$attr`";
            }
        };

        foreach ($attributes as $attr) {
            if (strpos($attr, ".") === false) {
                if (!array_key_exists($attr, $class_info["attributes"])) {
                    throw new \API\Exceptions\AttributeTypeNotFound($attr, "Attribute '$attr' on class '{$class_info["text_id"]}' not found");
                }

                $processAttr($table_name, $class_info, $attr, $attr);
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
                    if (empty($info["attributes"][$last_attr])) {
                        throw new \API\Exceptions\AttributeTypeNotFound($last_attr, "Attribute '$last_attr' on class '{$info["text_id"]}' not found");
                    }

                    if ($info["attributes"][$last_attr]["description"]["is_localizable"] == 1) {
                        throw new \InvalidArgumentException("Binding attribute don't support localization.");
                    }

                    $info = $info["attributes"][$last_attr]["specification"];

                    $addJoin($info["table"], "`{$info["table"]}`.`id` = `{$last_info["table"]}`.`{$last_attr}`");
                }

                if (!array_key_exists($c_attr, $info["attributes"])) {
                    throw new \API\Exceptions\AttributeTypeNotFound($c_attr, "Attribute '$c_attr' on class '{$info["text_id"]}' not found");
                }

                $processAttr($info["table"], $info, $c_attr, $attr);

                //$q_builder["base"]["columns"][] = "`{$info["table"]}`.`{$c_attr}` as `$attr`";

            }
        }

        $q = "SELECT " . join(",", $q_builder["base"]["columns"]) . " FROM `$table_name`";

        foreach ($q_builder["joins"] as $join) {
            if ($join["is_lang_table"]) {
                //$q .= " INNER JOIN {$join["table"]} as `{$join["table"]}` ON {$join["connector"]}";
                /*$q .= " INNER JOIN
                (SELECT * FROM {$join["table"]} WHERE
                    lang_id in ($lang_id, $def_lang)
                    AND
                    (lang_id = $lang_id NOT EXISTS (SELECT 1 FROM {$join["table"]} WHERE lang_id = $def_lang)) OR (NOT EXISTS (SELECT 1 FROM {$join["table"]} WHERE lang_id = 0))) as `{$join["table"]}` ON {$join["connector"]}";*/
                if ($def_lang != $lang_id) {
                    $q .= " LEFT JOIN (SELECT * FROM {$join["table"]} WHERE lang_id = {$lang_id}) as `{$join["table"]}` ON {$join["connector"]}";
                    $q .= " LEFT JOIN (SELECT * FROM {$join["table"]} WHERE lang_id = {$def_lang}) as `{$join["table_l2"]}` ON {$join["connector_l2"]}";
                }
                else {
                    $q .= " INNER JOIN (SELECT * FROM {$join["table"]} WHERE lang_id = {$def_lang}) as `{$join["table"]}` ON {$join["connector"]}";
                }
            }
            else {
                $q .= " INNER JOIN `{$join["table"]}` ON {$join["connector"]}";
            }
        }

        $q .= " WHERE (`{$class_info["table"]}`.`id` != 1)";

        if (!empty($condition)) $q .= " AND ". updateReaderCondition($condition, $table_name);
        if (!empty($filterCondition)) $q .= " AND (" . updateReaderCondition($filterCondition, $table_name) . ")";

        //$q .= " GROUP BY `$table_name`.id";

        if (!empty($order) && is_array($order)) {
            $o_q = "";
            foreach ($order as $ord) {
                if ($o_q != "") $o_q.= ", ";
                $o_attrName = $ord["attrName"];

                if (strtolower($o_attrName) == "rand()")  {
                    $o_q .= strtolower($o_attrName);
                    continue;
                }

                if (strpos($o_attrName, ".") !== false) {
                    $exp_attrName = explode(".", $o_attrName, 2);
                    $exist_class_order = getClassDescription($exp_attrName[0]);
                    if (!empty($exist_class_order)) {
                        $o_attrName = $exist_class_order["table"] . "." . $exp_attrName[1];
                    }
                }

                if ($o_attrName == "id") $o_attrName = "{$table_name}.".$o_attrName;
                $o_q .= "{$o_attrName}";
                if (!empty($ord["direction"])) $o_q .= " {$ord["direction"]}";
            }

            if (!empty($o_q)) {
                $q .= " ORDER BY {$o_q}";
            }
        }

        $offset = $page * $limit;
        $q .= " LIMIT {$limit} OFFSET $offset";

        //dump($q);

        $q_builder["query"] = $q;

        return $q_builder;
    }

    public function getClassData($classId, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $filterCondition = null, $filterParams = [], $lang_id = null) {
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

    public static function getTableData($table_name, $join_definition, $attributes, $page = 0, $limit = -1, $order = null, $condition = null, $lang_id = null) {
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

        $addJoin = function($table, $table_index, $connector) use (&$tables_in_join, &$q_builder) {
            if (!in_array($table_index, $tables_in_join)) {
                $q_builder["joins"][] = [
                    "table" => $table,
                    "connector" => $connector
                ];
                $tables_in_join[] = $table_index;
            }
        };

        $processAttr = function ($table, $c_attr, $attr) use (&$q_builder, $addJoin) {
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
            if (strpos($attr, ".") === false) {
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

    private static function updateReaderCondition($cond, $table_name) {

        $cond = preg_replace_callback("/([A-Za-z._]+)(?=[ ]*(?:[<>=]|LIKE))/", function($matches) use ($table_name) {
            if ($matches[0] == "id") return "{$table_name}.{$matches[0]}";

            return $matches[0];
        }, $cond);

        return $cond;
    }
}