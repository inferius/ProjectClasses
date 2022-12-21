<?php

namespace API;

require_once("AttributeValue.php");

class FileAttributeValue extends AttributeValue {
    public function __construct($value, $attr_info) {
        parent::__construct($value, $attr_info);
    }

    private $temp_to_delete = [];
    private $final_to_delete = null;

    public function setValue($value) {
        if (!empty($this->value)) {
            if ($this->isEdited) {
                $this->temp_to_delete[] = $this->value;
            }
            else {
                $this->final_to_delete = $this->value;
            }
        }

        $this->is_edited = true;
        $this->value = $value;
    }

    private function cleanAfterSave() {
        foreach ($this->temp_to_delete as $id_to_delete) $this->removeTempFile($id_to_delete);
        if (!empty($this->final_to_delete)) $this->removeFinalFile($this->final_to_delete);

        $this->final_to_delete = null;
        $this->temp_to_delete = [];
    }

    private function removeTempFile($id_to_delete) {
        global $config;

        $this->removeFile("fp_temp_files", $config["path"]["absolute"]["temp"], $id_to_delete);
    }


    private function removeFinalFile($id_to_delete) {
        global $config;
        global $connection;

        $files = $connection->query("SELECT * FROM fp_final_files WHERE group_id = ?", $id_to_delete);

        foreach ($files as $file) {
            @unlink($config["path"]["absolute"]["uploaded"] . $file["relative_path"]);
        }

        $connection->query("DELETE FROM fp_final_files WHERE group_id = ?", $id_to_delete);
    }

    private function removeFile($table_name, $start_path, $id_to_delete) {
        global $connection;

        if ($id_to_delete == null) return;
        $file_data = $connection->fetch("SELECT * FROM $table_name WHERE id = ?", $id_to_delete);
        if (empty($file_data)) return null;

        @unlink($start_path . $file_data["relative_path"]);
        $connection->fetch("DELETE FROM $table_name WHERE id = ?", $id_to_delete);
    }

    private function moveTempToFinal() {
        global $connection;
        global $config;

        $user = \API\Users::getLoggedUser();

        $file_data = $connection->fetch("SELECT * FROM fp_temp_files WHERE id = ?", $this->value);
        if (empty($file_data)) return null;

        $temp_file_path = $config["path"]["absolute"]["temp"] . $file_data["relative_path"];
        $final_file_path = $config["path"]["absolute"]["uploaded"] . $file_data["relative_path"];
        $final_file_dir = dirname($final_file_path);


        if (!is_dir($final_file_dir)) {
            @mkdir($final_file_dir, 0777, true);
            @chmod($final_file_dir, 0777);
        }

        //$connection->fetch("DELETE FROM fp_temp_files WHERE id = ?", $file_data["id"]);
        if (@copy($temp_file_path, $final_file_path)) {
            $connection->query("INSERT INTO fp_final_files ", [
                "text_id" => "original",
                "created" => $connection::literal("now()"),
                "created_by" => $user->getId(),
                "path" => $final_file_path,
                "relative_path" => $file_data["relative_path"],
                "file_id" => $file_data["file_id"],
                "original_file_name" => $file_data["original_file_name"],
                "mime_type" => $file_data["mime_type"],
                "size" => $file_data["size"],
            ]);

            //$this->value = $connection->getInsertId();
            $this->value = $connection->getInsertId();
            $connection->query("UPDATE fp_final_files SET group_id = ? WHERE id = ?", $this->value, $this->value);

            //@unlink($temp_file_path);
            //$connection->fetch("DELETE FROM fp_temp_files WHERE id = ?", $file_data["id"]);
            //$this->removeTempFile($file_data["id"]);
        }
    }

    private function processPostProcessing() {

    }

    public function getValue() {
        global $connection;
        global $config;

        if ($this->isEmpty()) return null;
        else {

            $tableName = "fp_final_files";
            $start_path = $config["path"]["absolute"]["uploaded"];
            $start_relative_path = $config["path"]["relative"]["uploaded"];
            if ($this->isEdited) {
                $tableName = "fp_temp_files";
                $start_path = $config["path"]["absolute"]["temp"];
                $start_relative_path = $config["path"]["relative"]["temp"];
            }

            $file_data = $connection->fetch("SELECT * FROM $tableName WHERE id = ?", $this->value);
            if (empty($file_data)) return null;

            $pp = [];

            if (!$this->isEdited) {
                $files_data = $connection->fetchAll("SELECT * FROM $tableName WHERE group_id = ?", $this->value);
                foreach ($files_data as $fd) {
                    $pp[$fd["text_id"]] = [
                        "name" => $fd["original_file_name"],
                        "url" => $start_relative_path . $fd["relative_path"],
                        "mime" => $fd["mime_type"],
                        "size" => $fd["size"],
                        "path" => $start_path . $fd["relative_path"],
                    ];
                }
            }

            return [
                "name" => $file_data["original_file_name"],
                "url" => $start_relative_path . $file_data["relative_path"],
                "mime" => $file_data["mime_type"],
                "size" => $file_data["size"],
                "path" => $start_path . $file_data["relative_path"],
                "postprocess" => $pp
            ];
        }
    }

    public function toFormat($alt = "", $config = []) {
        if ($this->isEmpty()) return null;
        else {
            return getUpladedImageLatte($this->value, $alt, $config);
        }
    }


    public function save() {
        if (!$this->isValid()) throw new \API\Exceptions\ValidationException("Value is not valid");
        $this->value = $this->attr_type->beforeSave($this->value);

        if ($this->is_edited) {
            $this->moveTempToFinal();
        }

        $this->is_edited = false;

        return $this->value;
    }

    public function afterSave() {
        $this->cleanAfterSave();
    }


}