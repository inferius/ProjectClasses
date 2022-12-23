<?php

namespace API\Functions;

class MaskFunction extends BaseFunction {

    public function execute($input_file, $target = null) {
        $file = MaskFunction::parseFile($input_file);
        parent::execute($file, $target);

        if (empty($file)) return null;

        if (is_string($file->getTransformedFile())) {
            $image = getImageManipulationInstance()->open($file->getTransformedFile());
        }
        else if (is_object($file)) {
            $image = $file->getTransformedFile();
        }

        if (empty($this->getArgumentValue("path"))) return $file;

        $mask = getImageManipulationInstance()->open($this->getArgumentValue("path"));

        if ($image->getSize()->getWidth() < $mask->getSize()->getWidth() || $image->getSize()->getWidth() > $mask->getSize()->getWidth()) {
            $mask->resize($mask->getSize()->widen($image->getSize()->getWidth()));
        }
        if ($image->getSize()->getHeight() < $mask->getSize()->getHeight() || $image->getSize()->getHeight() < $mask->getSize()->getHeight()) {
            $mask->resize($mask->getSize()->heighten($image->getSize()->getHeight()));
        }

        if ($this->hasArgument("autocrop")) $image->crop(new \Imagine\Image\Point(0, 0), new \Imagine\Image\Box($mask->getSize()->getWidth(), $mask->getSize()->getHeight()));
        //if ($this->hasArgument("autocrop")) $image->crop(new \Imagine\Image\Point(0, 0), new \Imagine\Image\Box($image->getSize()->getWidth(), $image->getSize()->getHeight()));

        $image->applyMask($mask);

        //if (!empty($target)) $image->save($target, [  ]);

        $file->transform($image);
        $file->switchToPng();
        $file->setSaveFunction(function ($img, $path) {
            $img->save($path, $this->convert_config);
        });

        return $file;
    }
}