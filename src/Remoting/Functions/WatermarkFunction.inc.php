<?php

namespace API\Functions;


class WatermarkFunction extends BaseFunction {

    public function execute($input_file, $target = null) {
        $file = WatermarkFunction::parseFile($input_file);
        parent::execute($file, $target);

        if (empty($file)) return null;

        if (is_string($file->getTransformedFile())) {
            $image = \API\FunctionCore::getImageManipulationInstance()->open($file->getTransformedFile());
        }
        else if (is_object($file)) {
            $image = $file->getTransformedFile();
        }

        if (empty($this->getArgumentValue("path"))) return $file;

        $watermark = \API\FunctionCore::getImageManipulationInstance()->open($this->getArgumentValue("path"));
        $point = new \Imagine\Image\Point(intval($this->getArgumentValue("x")), intval($this->getArgumentValue("y")));


        if (!empty($this->getArgumentValue("size"))) {
            $val = intval($this->getArgumentValue("size")) / 100;
            $watermark = $watermark->resize($watermark->getSize()->widen($watermark->getSize()->getWidth() * $val));
        }

        if (!empty($this->getArgumentValue("size_by_original"))) {
            $val = intval($this->getArgumentValue("size_by_original")) / 100;
            $watermark = $watermark->resize($image->getSize()->widen($image->getSize()->getWidth() * $val));
        }


        if ($image->getSize()->getWidth() < $watermark->getSize()->getWidth()) {
            $watermark = $watermark->resize($watermark->getSize()->widen($image->getSize()->getWidth()));
        }
        if ($image->getSize()->getHeight() < $watermark->getSize()->getHeight()) {
            $watermark = $watermark->resize($watermark->getSize()->heighten($image->getSize()->getHeight()));
        }



        $image->paste($watermark, $point);

        $file->transform($image);
        $file->setSaveFunction(function ($img, $path) {
            $img->save($path, $this->convert_config);
        });

        //if (!empty($target)) $image->save($target);

        return $file;
    }

}