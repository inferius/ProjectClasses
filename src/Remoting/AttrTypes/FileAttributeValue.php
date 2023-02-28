<?php

namespace API;

use API\Model\IAttributeInfo;
use Imagine\Image\ImageInterface;

class FileAttributeValue extends AttributeValue {
    private $original_value = null;
    public function __construct($value, IAttributeInfo $attr_info, $class_name) {
        parent::__construct($value, $attr_info);

        $this->original_value = $value;
        $this->postprocess_info = new \API\PostprocessData($class_name, $attr_info->getAlias());
    }

    /**
     * @var PostprocessData
     */
    private $postprocess_info;

    private $temp_to_delete = [];
    private $final_to_delete = null;

    private $image_extension = [ "jpg", "jpeg", "jfif", "pjpeg", "pjp", "apng", "svg", "png", "gif", "webp", "avif" ];
    private $image_size = [ 350, 410, 600, 745, 860, 970, 1200, 1600, 2400, 3200 ];

    private $convert_config = [
        "jpeg_quality" => 85,
        "png_compression_level" => 3,
        "webp_quality" => 85,
        "avif_quality" => 85
    ];

    /**
     * @return PostprocessData
     */
    public function getPostProcess() {
        return $this->postprocess_info;
    }

    public function setPostProcess($value) {
        if (is_string($value) || is_int($value)) {
            $this->postprocess_info = new \API\PostprocessData(intval($value));
        }
        else {
            $this->postprocess_info = $value;
        }
    }

    public function setValue($value): void {
        if (!empty($this->value)) {
            if ($this->isEdited()) {
                $this->temp_to_delete[] = $this->value;
            }
            else {
                $this->final_to_delete = $this->value;
            }
        }

        $this->is_edited = true;

        if (is_array($value)) {
            $this->value = $value["file"];
            if (!empty($value["data"]["postprocess_override"])) {
                if ($value["data"]["postprocess_override"] == -1) {
                    $this->setPostProcess(null);
                }
                else {
                    $this->setPostProcess($value["data"]["postprocess_override"]);
                }
            }
        }
        else {
            $this->value = $value;
        }
    }

    private function cleanAfterSave() {
        foreach ($this->temp_to_delete as $id_to_delete) $this->removeTempFile($id_to_delete);
        if (!empty($this->final_to_delete)) $this->removeFinalFile($this->final_to_delete);

        $this->final_to_delete = null;
        $this->temp_to_delete = [];
    }

    private function removeTempFile($id_to_delete) {
        $this->removeFile("fp_temp_files", \API\Configurator::$config["path"]["absolute"]["temp"], $id_to_delete);
    }


    private function removeFinalFile($id_to_delete) {
        $files = \API\Configurator::$connection->query("SELECT * FROM fp_final_files WHERE group_id = ?", $id_to_delete);

        foreach ($files as $file) {
            @unlink(\API\Configurator::$config["path"]["absolute"]["uploaded"] . $file["relative_path"]);
            @unlink(\API\Configurator::$config["path"]["absolute"]["uploaded"] . $file["relative_original_path"]);
            if (!empty($file["format_info"])) {
                $file_info = json_decode($file["format_info"], true);
                if (!empty($file_info["created_files"]["all"])) {
                    foreach ($file_info["created_files"]["all"] as $file_thumb) {
                        @unlink(\API\Configurator::$config["path"]["absolute"]["uploaded"] . $file_thumb["relative"]);
                    }
                }
            }
        }

        \API\Configurator::$connection->query("DELETE FROM fp_final_files WHERE group_id = ?", $id_to_delete);
    }

    private function removeFile($table_name, $start_path, $id_to_delete) {
        

        if ($id_to_delete == null) return;
        $file_data = \API\Configurator::$connection->fetch("SELECT * FROM $table_name WHERE id = ?", $id_to_delete);
        if (empty($file_data)) return null;

        @unlink($start_path . $file_data["relative_path"]);
        \API\Configurator::$connection->query("DELETE FROM $table_name WHERE id = ?", $id_to_delete);
    }

    private function moveTempToFinal() {
        $unlink_after_copy = [];

        $user = \API\Users::getLoggedUser();

        $tmp_file_id = $this->value;

        $file_data = \API\Configurator::$connection->fetch("SELECT * FROM fp_temp_files WHERE id = ?", $tmp_file_id);
        if (empty($file_data)) return null;

        $temp_file_path = \API\Configurator::$config["path"]["absolute"]["temp"] . $file_data["relative_path"];

        $pi_temp = pathinfo($temp_file_path);
        $final_original_torso = str_replace([\API\Configurator::$config["path"]["absolute"]["temp"], $pi_temp["basename"]], "", $temp_file_path);
        $final_original_relative = $final_original_torso . "/" . $pi_temp["filename"] . "_original.{$pi_temp["extension"]}";
        $final_original_path = \API\Configurator::$config["path"]["absolute"]["uploaded"] . $final_original_relative;

        $final_file_path = \API\Configurator::$config["path"]["absolute"]["uploaded"] . $file_data["relative_path"];
        $final_file_dir = dirname($final_file_path);

        if (!is_dir($final_file_dir)) {
            @mkdir($final_file_dir, 0777, true);
            @chmod($final_file_dir, 0777);
        }

        @copy($temp_file_path, $final_original_path);


        if ($this->getPostProcess() != null && $this->getPostProcess()->getData() != null) {
            $file = $this->processPostProcessing($temp_file_path);
            $file->save();
            //$unlink_after_copy[] = $file->getPath();
            $temp_file_path = $file->getPath();
            $file_data["relative_path"] = str_replace(\API\Configurator::$config["path"]["absolute"]["temp"], "", $temp_file_path);
            $file_data["mime_type"] = FunctionCore::getMimeTypeByPath($temp_file_path);

            $ofn = pathinfo($file_data["original_file_name"]);
            $file_data["original_file_name"] = $ofn["filename"] . "." . $file->getTargetExtension();

            // v postprocesu se mohla zmenit pripona a je potreba to provest znova
            $final_file_path = \API\Configurator::$config["path"]["absolute"]["uploaded"] . $file_data["relative_path"];
        }



        //\API\Configurator::self::$$connection->fetch("DELETE FROM fp_temp_files WHERE id = ?", $file_data["id"]);
        if (@copy($temp_file_path, $final_file_path)) {
            //foreach ($unlink_after_copy as $uac) @unlink($uac);

            /** @noinspection */
            \API\Configurator::$connection->query("INSERT INTO fp_final_files ", [
                "text_id" => "original",
                "created" => \API\Configurator::$connection::literal("now()"),
                "created_by" => $user->getId(),
                "path" => $final_file_path,
                "relative_original_path" => $final_original_relative,
                "relative_path" => $file_data["relative_path"],
                "file_id" => $file_data["file_id"],
                "original_file_name" => $file_data["original_file_name"],
                "mime_type" => $file_data["mime_type"],
                "size" => $file_data["size"],
            ]);

            $this->value = \API\Configurator::$connection->getInsertId();
            \API\Configurator::$connection->query("UPDATE fp_final_files SET group_id = ? WHERE id = ?", $this->value, $this->value);

            //@unlink($temp_file_path);
            //\API\Configurator::self::$$connection->fetch("DELETE FROM fp_temp_files WHERE id = ?", $file_data["id"]);
            //$this->removeTempFile($file_data["id"]);
            //$this->removeTempFile($tmp_file_id);
            $this->temp_to_delete[] = $tmp_file_id;

            if (\Nette\Utils\Strings::startsWith($file_data["mime_type"], "image/")) {
                $finfo = $this->defaultFilePostProcess($final_file_path);
                \API\Configurator::$connection->query("UPDATE fp_final_files SET format_info = ? WHERE id = ?", json_encode($finfo), $this->value);
            }
        }
    }

    private function defaultFilePostProcess($path) {
        $info = pathinfo($path);
        //$mime_type = mime_content_type($path);

        if (!in_array($info["extension"], $this->image_extension)) return null;

        //function_exists("imageavif"),
        //function_exists("imagewebp"),
        //function_exists("imagejpeg")

        $relative_dir = str_replace(\API\Configurator::$config["path"]["absolute"]["uploaded"], "", $info["dirname"]);
        $img = FunctionCore::getImageManipulationInstance();
        $di = FunctionCore::getImageDriverInfo();
        $img_instance = $img->open($path);

        $img_sizes = $this->image_size;
        arsort($img_sizes, SORT_NUMERIC);
        $target_extenstion_all = [ "jpg" => [ "jpg", "jfif", "jpeg", "jpe", "jif", "jfi"], "png", "avif", "webp" ];

        $finfo = [];

        $base_ext = "jpg";

        if ($info["extension"] == "png") $base_ext = "png";

        $finfo["base"] = $base_ext;
        $finfo["supported_formats"] = [];
        $finfo["supported_width"] = [];
        $finfo["created_files"] = [ "all" => [], "formats" => [] ];
        $finfo["info"] = [
            "original_width" => $img_instance->getSize()->getWidth(),
            "original_height" => $img_instance->getSize()->getHeight(),
        ];

        //$cur_img = $img_instance->copy();
        $cur_img = $img_instance;

        // pokud je obrazek
        if (in_array(strtolower($info["extension"]), $this->image_extension)) {
            $target_extenstion_fl = [ "avif", "webp", $base_ext ];
            $src_set_data = "srcset";
            $target_path_relative = $relative_dir . "/" . $src_set_data;
            $target_path = \API\Configurator::$config["path"]["absolute"]["uploaded"] . "/$target_path_relative";

            $finfo["subfolder"] = $src_set_data;

            if (!is_dir($target_path)) {
                mkdir($target_path, 0777);
                chmod($target_path, 0777);
            }


            foreach ($img_sizes as $image_width) {
                // pokud je sirka originalniho obrazku mensi, optimalizaci preskocime
                if ($img_instance->getSize()->getWidth() < $image_width) continue;
                $cur_img->resize($cur_img->getSize()->widen($image_width));
                $finfo["supported_width"][] = $image_width;

                foreach ($target_extenstion_fl as $te) {
                    if (!$di->isFormatSupported($te)) continue;
                    if (empty($finfo["created_files"]["formats"][$te])) {
                        $finfo["created_files"]["formats"][$te] = [];
                        $finfo["supported_formats"][] = $te;

                        // Prekonvertovani puvodni velikosti na dany format
                        /*$original_fname = $info["filename"] . "_original";
                        $original_convert_path = $target_path . "/" . $original_fname . ".$te";
                        $img_instance->save($original_convert_path);

                        $original_finfo_line = [
                            "basename" => $original_fname,
                            "extension" => $te,
                            "name" => $original_fname . ".$te",
                            "path" => $original_convert_path,
                            "relative" => $target_path_relative . "/$original_fname.$te"
                        ];

                        $finfo["created_files"]["formats"][$te]["original"] = $original_finfo_line;
                        $finfo["created_files"]["all"][] = $original_finfo_line;*/
                    }
                    $fname = $info["filename"] . "_{$image_width}w";
                    $cur_path = $target_path . "/" . $fname . ".$te";
                    $cur_img->save($cur_path, $this->convert_config);

                    $finfo_line = [
                        "basename" => $fname,
                        "extension" => $te,
                        "name" => $fname . ".$te",
                        "path" => $cur_path,
                        "relative" => $target_path_relative . "/$fname.$te"
                    ];
                    $finfo["created_files"]["formats"][$te][$image_width] = $finfo_line;
                    $finfo["created_files"]["all"][] = $finfo_line;
                }
            }

        }

        return $finfo;
    }

    private function processPostProcessing($tmp_file_path) {
        return $this->getPostProcess()->processMethods($tmp_file_path);
    }

    public function getValue() {
        if ($this->isEmpty()) return null;
        else {

            $tableName = "fp_final_files";
            $start_path = \API\Configurator::$config["path"]["absolute"]["uploaded"];
            $start_relative_path = \API\Configurator::$config["path"]["relative"]["uploaded"];
            if ($this->isEdited()) {
                $tableName = "fp_temp_files";
                $start_path = \API\Configurator::$config["path"]["absolute"]["temp"];
                $start_relative_path = \API\Configurator::$config["path"]["relative"]["temp"];
            }

            $file_data = \API\Configurator::$connection->fetch("SELECT * FROM $tableName WHERE id = ?", $this->value);
            if (empty($file_data)) return null;

            $pp = null;
            if (!empty($this->getPostProcess())) {
                $pp = $this->getPostProcess()->getData();
            }
            $link_files = [];

            if (!$this->isEdited()) {
                $files_data = \API\Configurator::$connection->fetchAll("SELECT * FROM $tableName WHERE group_id = ?", $this->value);
                foreach ($files_data as $fd) {
                    $link_files[$fd["text_id"]] = [
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
                "id" => $file_data["id"],
                "original_file" => $start_relative_path . $file_data["relative_original_path"],
                "link_files" => $link_files,
                "postprocess" => $pp
            ];
        }
    }

    public function toFormat($alt = "", $config = []) {
        if ($this->isEmpty()) return null;
        else {
            return FunctionCore::getUpladedImageLatte($this->value, $alt, $config);
        }
    }

    public function delete(): void {
        $this->setValue(null);
        $this->is_edited = false;
        $this->cleanAfterSave();
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

    public function afterSave(): void {
        $this->cleanAfterSave();
    }


}