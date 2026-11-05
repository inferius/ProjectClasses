<?php

declare(strict_types=1);

namespace API\Functions;

class FileInfoData {
    private ?string $originalFile = null;

    //#[ArrayShape(['dirname' => 'string', 'basename' => 'string', 'extension' => 'string', 'filename' => 'string'])]
    private ?array $fileInfo = null;
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

    public function switchToPng(): void {
        $this->targetExtension = "png";
    }

    public function switchToJpeg(): void {
        $this->targetExtension = "jpg";
    }

    /**
     * @param $file
     * @return $this
     */
    public function transform($file): static {
        $this->transformedFile = $file;
        return $this;
    }

    public function getPath(): string {
        return "{$this->fileInfo["dirname"]}/{$this->getFileName()}";
    }

    public function getFileName(): string {
        $r = $this->fileInfo["filename"];
        if (!empty($this->postfix)) $r.= "_{$this->postfix}";
        $r .= ".{$this->targetExtension}";

        return $r;
    }

    public function setSaveFunction($fnc): void {
        $this->saveFunction = $fnc;
    }

    public function save(): void {
        if (is_callable($this->saveFunction)) {
            call_user_func($this->saveFunction, $this->transformedFile, $this->getPath());
        }
    }
}