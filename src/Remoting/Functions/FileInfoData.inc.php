<?php

namespace API\Functions;

class FileInfoData {
    private $originalFile;

    //#[ArrayShape(['dirname' => 'string', 'basename' => 'string', 'extension' => 'string', 'filename' => 'string'])]
    private $fileInfo;
    private $transformedFile;
    private $postfix;
    private $targetExtension;

    private $saveFunction = null;

    /**
     * @param string $file
     * @throws \Exception
     */
    public function __construct($file, $target = null) {
        if (is_string($file) && is_file($file)) {
            $this->originalFile = $file;
            $this->fileInfo = pathinfo($file);
            $this->targetExtension = $this->fileInfo["extension"];
            $this->transformedFile = $file;

            return;
        }

        throw new \Exception("File must be string and must exist");
    }

    public function getTargetExtension() {
        return $this->targetExtension;
    }

    public function getOriginalFile() {
        return $this->originalFile;
    }

    public function getTransformedFile() {
        return $this->transformedFile;
    }

    public function switchToPng() {
        $this->targetExtension = "png";
    }

    public function switchToJpeg() {
        $this->targetExtension = "jpg";
    }

    /**
     * @param $file
     * @return $this
     */
    public function transform($file) {
        $this->transformedFile = $file;
        return $this;
    }

    public function getPath() {
        return "{$this->fileInfo["dirname"]}/{$this->getFileName()}";
    }

    public function getFileName() {
        $r = $this->fileInfo["filename"];
        if (!empty($this->postfix)) $r.= "_{$this->postfix}";
        $r .= ".{$this->targetExtension}";

        return $r;
    }

    public function setSaveFunction($fnc) {
        $this->saveFunction = $fnc;
    }

    public function save() {
        if (is_callable($this->saveFunction)) {
            call_user_func($this->saveFunction, $this->transformedFile, $this->getPath());
        }
    }
}