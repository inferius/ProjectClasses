<?php

namespace API;

class PostprocessData {
    private $data;
    public function __construct($class_name, $attr_alias = null) {
        if (is_int($class_name)) {
            $this->data = \API\Configurator::$connection->fetch("SELECT * FROM fp_files_postprocess_template WHERE id = ?", $class_name);
        }
        else {
            $this->data =  \API\Configurator::$connection->fetch("SELECT fpt.* FROM fp_files_postprocess_class_attr_posprocess AS fpc 
    INNER JOIN fp_files_postprocess_template AS fpt ON fpt.id = fpc.postprocess_id 
             WHERE fpc.class_name = ? AND fpc.attr_alias = ?", $class_name, $attr_alias);
        }
    }


    public function getData() {
        return $this->data;
    }

    private function getAttribute($name) {
        if (!empty($this->data)) return $this->data[$name];
        return null;
    }

    public function getId() {
        return $this->getAttribute("id");
    }

    public function getName() {
        return $this->getAttribute("name");
    }

    /**
     * @return string[]
     */
    public function getAllowedExtensions() {
        $allowed_ext = $this->getAttribute("allowed_extension");
        if (empty($allowed_ext)) return [];

        return explode(",", $allowed_ext);
    }

    public function getMaxSize() {
        return $this->getAttribute("max_size");
    }

    public function getMinSize() {
        return $this->getAttribute("min_size");
    }

    /**
     * @return string[]
     */
    public function getAllowedMimes() {
        $mimes = $this->getAttribute("allowed_mime");
        if (empty($allowed_ext)) return [];

        return explode(",", $mimes);
    }

    public function keepOriginal() {
        return boolval($this->getAttribute("keep_original"));
    }

    public function getMethodBody() {
        return $this->getAttribute("method_body");
    }

    public function getImageEditConfig() {
        return $this->getAttribute("image_edit_config");
    }

    /**
     * @param $file
     * @param $target
     * @return Functions\FileInfoData|mixed|null
     */
    public function processMethods($file, $target = null) {
        if (empty($this->getMethodBody())) return $file;

        $lines = array_map(function($item) {
            return \API\Functions\BaseFunction::parse(str_replace("\r", "", $item));
        }, explode("\n", $this->getMethodBody()));

        $updated_file = $file;
        foreach ($lines as $item) {
            $updated_file = $item->execute($updated_file, $target);
        }

        return $updated_file;
    }
}
