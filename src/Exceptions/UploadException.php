<?php

declare(strict_types=1);

namespace API\Exceptions;

class UploadException extends \Exception
{
    public function __construct($code) {
        // kod z $_FILES je int; retezec by v match (striktni porovnani) skoncil jako neznama chyba
        $code = (int)$code;
        $message = $this->codeToMessage($code);
        parent::__construct($message, $code);
    }

    private function codeToMessage($code): string
    {
        $message = match ($code) {
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "File upload stopped by extension",
            default => "Unknown upload error",
        };
        return $message;
    }
}