<?php



function createTable($table_name, $attrlist, $transaction_started = false) {
    global $connection;
    
    $attr_text_ids = array_unique(array_map(function($item) { return $item["attrName"]; }, $attrlist));
    $attrs_types_sql = $connection->fetchAll("SELECT * FROM model_attr_types WHERE text_id in (?)", $attr_text_ids);

    $attr_types_data = [];
    foreach ($attrs_types_sql as $attr_type) {
        $attr_types_data[$attr_type["text_id"]] = $attr_type;
    }

    try {
        if (!$transaction_started) $connection->beginTransaction();

        $q_string_non_localizable = "";
        $q_string_localizable = "";

        $q_string_loc = "CREATE TABLE IF NOT EXISTS `{$table_name}_lang_data` (" .
        "  `id` bigint(20) NOT NULL AUTO_INCREMENT,".
        "  `parent_id` bigint(20) NOT NULL,".
        "  `lang_id` bigint(20) NOT NULL";

        $q_string = "CREATE TABLE IF NOT EXISTS `{$table_name}` (" .
            "  `id` bigint(20) NOT NULL AUTO_INCREMENT,  
            `created` timestamp NULL DEFAULT NULL,
            `edited` timestamp NULL DEFAULT NULL";

        $q_end = "  ,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;";

        foreach ($attrlist as $attr_info) {
            $attr = $attr_types_data[$attr_info["attrName"]];
            $q_t_string = ", " . getTableColum($attr_info["alias"], $attr);

            if ($attr["is_localizable"] == 1) {
                $q_string_localizable .= $q_t_string;
            }
            else {
                $q_string_non_localizable .= $q_t_string;
            }
        }

        $connection->query($q_string . $q_string_non_localizable . $q_end);
        $connection->query($q_string_loc . $q_string_localizable . $q_end);

        $connection->query("INSERT INTO {$table_name}", [ "created" => $connection::literal("now()") ]);
        $connection->query("INSERT INTO {$table_name}_lang_data", [ "lang_id" => 0, "parent_id" => 1 ]);

        $connection->commit();
    }
    catch (\Exception $e) {
        $connection->rollBack();
        var_dump($e);
    }
}

function updateTable($table_name, $attrlist) {
    global $connection;
    //$attrs = $connection->fetchAll("SELECT * FROM model_attr_types WHERE text_id in (?)", $attrlist);
    $attr_text_ids = array_unique(array_map(function($item) { return $item["attrName"]; }, $attrlist));
    $attrs_types_sql = $connection->fetchAll("SELECT * FROM model_attr_types WHERE text_id in (?)", $attr_text_ids);

    $attr_types_data = [];
    foreach ($attrs_types_sql as $attr_type) {
        $attr_types_data[$attr_type["text_id"]] = $attr_type;
    }

    try {
        $connection->beginTransaction();
        foreach ($attrlist as $attr_info) {
            $attr = $attr_types_data[$attr_info["attrName"]];
            $q_t_string = getTableColum($attr_info["alias"], $attr);

            if ($attr["is_localizable"] == 1) {
                $connection->query("ALTER TABLE {$table_name}_lang_data ADD COLUMN $q_t_string");
            }
            else {
                $connection->query("ALTER TABLE {$table_name} ADD COLUMN $q_t_string");
            }
        }
        $connection->commit();
    }
    catch (\Exception $e) {
        $connection->rollBack();
    }
}

function getTableColum($colName, $attr) {
    $q_t_string = "`{$colName}`";


    $q_t_string .= " ";
    switch ($attr["data_type"]) {
        case "string": $q_t_string .= "varchar(400) COLLATE utf8mb4_czech_ci"; break;
        case "text": $q_t_string .= "mediumtext COLLATE utf8mb4_czech_ci"; break;
        case "bool":
        case "boolean": $q_t_string .= "tinyint(1)"; break;
        case "int":
        case "enum":
        case "file":
        case "class": $q_t_string .= "int(11)"; break;
        case "date": $q_t_string .= "date"; break;
        case "time": $q_t_string .= "time"; break;
        case "datetime": $q_t_string .= "datetime"; break;
    }

    //if ($attr["is_required"] != "1") {
        if ($attr["data_type"] == "class") $q_t_string .= " DEFAULT '1'";
        else $q_t_string .= " DEFAULT NULL";
    //}
    //else {
    //    $q_t_string .= " NOT NULL";
    //}

    return $q_t_string;
}


function getClassDescription($class_text_id) {
    global $connection;
    global $memcache;

    $cache_key = "ModelClassDescription:" . $class_text_id;

    if (class_exists("Memcache")) {
        $r = $memcache->get($cache_key);
        if (!empty($r)) return $r;
    }
    //var_dump($class_text_id);


    $class_info = $connection->fetch("SELECT * FROM model_classes WHERE text_id = ?", $class_text_id);
    //$attrs = $connection->query("SELECT mat.*, mca.attr_alias FROM model_attr_types AS mat INNER JOIN model_classes_attrs AS mca ON mat.text_id = mca.a_txt_id WHERE mca.c_txt_id = ?", $class_text_id);
    $attrs = $connection->query("SELECT mat.*, mca.attr_alias FROM model_attr_types AS mat INNER JOIN model_classes_attrs AS mca ON mat.text_id = mca.a_txt_id WHERE mca.c_txt_id = ?", $class_text_id);

    $data = [
        "name" => $class_info["class_name"],
        "text_id" => $class_info["text_id"],
        "table" => $class_info["table_name"],
        "attributes" => []
    ];

    foreach ($attrs as $attr) {
        $data["attributes"][$attr["attr_alias"]] = [
            "type" => $attr["data_type"],
            "text_id" => $attr["text_id"],
            "alias" => $attr["attr_alias"],
            "description" => $attr,
            "specification" => null
        ];
        if ($attr["data_type"] == "class") {
            $data["attributes"][$attr["attr_alias"]]["specification"] = getClassDescription($attr["data_subtype"]);
        }
        else if ($attr["data_type"] == "enum") {
            $data["attributes"][$attr["attr_alias"]]["specification"] = getEnumDescription($attr["data_subtype"]);
        }
        /*else if ($attr["data_type"] == "file") {
            $data["attributes"][$attr["attr_alias"]]["specification"] = getFilePostprocess($class_info["text_id"], $attr["text_id"]);
        }*/
    }

    if (class_exists("Memcache")) {
        $memcache->set($cache_key, $data);
    }

    return $data;
}

function getEnumDescription($enum_txt_id) {
    global $connection;

    $enum_info = $connection->fetch("SELECT * FROM model_enums WHERE text_id = ?", $enum_txt_id);

    $enum_items = $connection->fetchAll("SELECT text_id, lockey as name FROM model_enums_item WHERE parent_id = ?", $enum_info["id"]);

    return [
        "name" => $enum_info["name"],
        "text_id" => $enum_info["text_id"],
        "description" => $enum_info["description"],
        "items" => $enum_items
    ];
}

function getFilePostprocess($c_txt_id, $a_txt_id) {
    global $connection;

    return $connection->fetchAll("SELECT fp.text_id, fp.method_body FROM model_classes_attrs_f_posprocess AS mcap INNER JOIN files_postprocess AS fp ON fp.text_id = mcap.pp_txt_id WHERE mcap.a_txt_id = ? AND mcap.c_txt_id = ?", $a_txt_id, $c_txt_id);
}

/**
 * Nacita data s trid
 */
function getDataFromClass($classId, $attributes, $page = 0, $limit = 50, $order = null, $condition = null) {
    $class_info = getClassDescription($classId);
    $table_name = $class_info["table"];

    $lang_id = $_SESSION[SESS_LANG]["id"];

    $q_builder = [
        "query" => null,
        "base" => [
            "table" => $table_name,
            "columns" => ["{$table_name}.id"]
        ],
        "joins" => [],
    ];

    $tables_in_join = [];

    $q = "";

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

    $processAttr = function ($table, $attr_info, $c_attr, $attr) use (&$q_builder, $addJoin) {

        if ($attr_info["attributes"][$c_attr]["description"]["is_localizable"] == 1) {
            $q_builder["base"]["columns"][] = "`{$table}_lang_data`.`{$c_attr}` as `$attr`";
            $addJoin("{$table}_lang_data", "`{$table}`.`id` = `{$table}_lang_data`.`parent_id`", true);
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
           $q .= " INNER JOIN (SELECT * FROM {$join["table"]} WHERE lang_id = 0 OR lang_id = $lang_id) as `{$join["table"]}` ON {$join["connector"]}";
        }
        else {
            $q .= " INNER JOIN `{$join["table"]}` ON {$join["connector"]}";
        }
    }


    $q .= " WHERE (`{$class_info["table"]}`.`id` != 1)";

    if (!empty($condition)) $q .= " AND ". updateReaderCondition($condition, $table_name);

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

        if (!empty($o_q)) $q .= " ORDER BY {$o_q}";
    }

    $offset = $page * $limit;
    $q.= " LIMIT {$limit} OFFSET $offset";

    $q_builder["query"] = $q;

    return $q_builder;
}

function updateReaderCondition($cond, $table_name) {

    $cond = preg_replace_callback("/([A-Za-z._]+)(?=[ ]*(?:[<>=]|LIKE))/", function($matches) use ($table_name) {
        if ($matches[0] == "id") return "{$table_name}.{$matches[0]}";

        return $matches[0];
    }, $cond);

    return $cond;
}

function getDataByTable($table_name, $join_definition, $attributes, $page = 0, $limit = 50, $order = null, $condition = null) {
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
        $q_w .= updateReaderCondition($condition, $table_name);

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

    $offset = $page * $limit;
    $q.= " LIMIT {$limit} OFFSET $offset";
    //var_dump($q);

    $q_builder["query"] = $q;

    return $q_builder;
}

function getUserPermission($user_groups, $req_perm) {
    foreach ($user_groups as $ug) {
        if (in_array($ug, $req_perm)) return true;
    }

    return false;
}

function getAttributeInfo($classId, $attributeName) {
    $attr = $attributeName;
    $class_info = getClassDescription($classId);

    if (strpos($attr, ".") === false) {
        return empty($class_info["attributes"][$attr]) ? null : $class_info["attributes"][$attr];
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
                return null;
            }

            if ($info["attributes"][$last_attr]["description"]["is_localizable"] == 1) {
                throw new \InvalidArgumentException("Binding attribute don't support localization.");
            }

            $info = $info["attributes"][$last_attr]["specification"];
        }

        return empty($info["attributes"][$c_attr]) ? null : $info["attributes"][$c_attr];
    }
}


function getFullDataFromClass($classId, $attributes, $page = 0, $limit = 50, $order = null, $condition = null) {
    global $connection;
    global $config;

    $prepared = getDataFromClass($classId, $attributes, $page, $limit, $order, $condition);

    try {
        $full_data_sql = $connection->query($prepared["query"]);
    }
    catch (\Exception $e) {
        if ($config["debug"]["status"]) dump($e);
        else throw $e;
    }
    //$class_info = getClassDescription($classId);
    //if ($classId == "product") dump($prepared);

    $r_value = [];
    $f_ids = [];

    $file_attrs = [];
    $file_map = [];

    foreach ($attributes as $attr) {
        $attr_info = getAttributeInfo($classId, $attr);
        if ($attr_info["type"] == "file") $file_attrs[] = $attr;
    }

    foreach ($full_data_sql as $item) {
        $r_row_value = [
            "id" => [
                "raw" => $item["id"],
                "value" => $item["id"],
            ]
        ];
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
        $files = $connection->query("SELECT * FROM fp_final_files WHERE id in (?)", $f_ids);

        foreach ($files as $file) {
            $file_map[$file["id"]] = $file;
            $file_map[$file["id"]]["postprocess"] = $connection->fetchAll("SELECT * FROM fp_final_files WHERE group_id in (?)", $f_ids);;
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

function getCMSVar($key) {
    global $connection;
    $key_m = "cms_var_key:$key";
    if (getMemCache($key_m)) return getMemCache($key_m);

    $main = $connection->fetch("SELECT * FROM _mct_variables_to_cms WHERE text_id = ?", $key);
    if (empty($main)) return "";

    $lang_v = $connection->fetch("SELECT * FROM _mct_variables_to_cms_lang_data WHERE parent_id = ? AND lang_id = ?", $main["id"], $_SESSION[SESS_LANG]["id"]);
    if (empty($lang_v)) {
        $lang_v = $connection->fetch("SELECT * FROM _mct_variables_to_cms_lang_data WHERE parent_id = ? AND lang_id = ?", $main["id"], 2);
    }
    if (empty($lang_v)) return "";
    setMemCache($key_m, $lang_v["short_text"], strtotime("+1 days"));

    return $lang_v["short_text"];
}

/**
 * Nahradí promenne v textu uvedene jako {$promenna} za hodnotu
 * @param $text string, ktery se ma projit
 * @param $params
 * @return array|string|string[]
 */
function replaceParamsInText($text, $params = []) {
    return preg_replace_callback('/{(?<main>\$(?<plain>[\w_\-]*))(?::(?<param>[\w_\-|]*))?}/m', function ($matches) use ($params) {
        global $setting;

        if (!empty($matches)) {
            if ($matches["main"] == '$setting' && !empty($matches["param"])) return $setting->getValue($matches["param"]);
            else if ($matches["main"] == '$static_page_url' && !empty($matches["param"])) return getStaticPageUrl($matches["param"]);
            else if ($matches["main"] == '$cms_page_url' && !empty($matches["param"])) return getCmsPageUrl($matches["param"]);
            else if ($matches["main"] == '$var' && !empty($matches["param"])) return getCMSVar($matches["param"]);
            else {
                if (!empty($params[$matches["plain"]])) return $params[$matches["plain"]];
            }
        }

        return "";
    }, $text);


}

/**
 * Vytvoří \PHPMailer pomocí šablony a automatický naplní Subject a Body na základě šablon
 * @param string $template_id Načte šablonu z třídy email_templates
 * @param array $params
 * @param bool|number $force_language Vynucený jazyk emailu, pokud je false, je použit aktuální jazyk.
 * @return \PHPMailer\PHPMailer\PHPMailer
 */
function createMailerFromTemplate(string $template_id, array $params = [], $force_language = false): \PHPMailer\PHPMailer\PHPMailer {
    $mailer = getMailer();

    $o = \API\BaseObject::getObjectByAttr("email_templates", "text_id", $template_id);
    if (!empty($force_language)) $o->setLanguage($force_language);

    $mailer->Subject = replaceParamsInText($o->getValue("subject"), $params);

    if (!empty($o->getValue("static_template_name"))) {
        // TODO: Doladit statické šablony
    }
    else {
        $mailer->Body = replaceParamsInText($o->getValue("content"), $params);
    }

    $mailer->isHTML(true);

    return $mailer;
}

function file_get_contents_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}