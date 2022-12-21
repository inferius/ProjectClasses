<?php

namespace API;

require_once("AttributeValue.php");

class FileAttributeValue extends AttributeValue {
    public function __construct($value, $attr_info) {
        parent::__construct($value, $attr_info);
    }

    public function getValue() {
        global $connection;
        global $config;

        if ($this->isEmpty()) return null;
        else {
            $file_data = $connection->fetch("SELECT * FROM fp_temp_files WHERE id = ?", $this->value);
            if (empty($file_data)) return null;

            return [
                "name" => $file_data["original_file_name"],
                "url" => $file_data["relative_path"],
                "mime" => $file_data["mime_type"],
                "size" => $file_data["size"],
                "path" => $config["path"]["absolute"]["root"] . $file_data["relative_path"]
            ];
        }
    }


}