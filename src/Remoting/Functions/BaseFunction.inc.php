<?php

declare(strict_types=1);

namespace API\Functions;

class BaseFunction {
    private array $arguments = [];
    private $name;
    // vychozi kvalita vystupu funkci (vodoznak, maska) - config "image_quality" ji prepise (FileAttributeValue::convertConfig)
    protected $convert_config = [
        "jpeg_quality" => 90,
        "png_compression_level" => 3,
        "webp_quality" => 90,
        "avif_quality" => 90
    ];

    /**
     * @param $name
     * @return Argument|null
     */
    public function getArgument($name): ?Argument {
        if (!isset($this->arguments[$name])) return null;
        return $this->arguments[$name];
    }

    public function hasArgument($name): bool {
        $name = strtolower($name);
       return isset($this->arguments[$name]);
    }

    public function getArgumentValue($name) {
        $name = strtolower($name);
        if (!isset($this->arguments[$name])) return null;
        return $this->arguments[$name]->getValue();
    }

    public function getName() {
        return $this->name;
    }

    /**
     * @return non-empty-string[]
     */
    private static function _explode($input): array {
        $lng = mb_strlen($input, "utf8");

        $ret = [];
        $buffer = "";

        $string_chars = [ "\"", "'" ];

        $escape = false;
        $in_string = false;
        $string_char = '';
        for ($i = 0; $i < $lng; $i++) {
            $ch = $input[$i];

            if ($in_string) {
                if ($ch == "\\") {
                    if (!$escape) $escape = true;
                    else $escape = false;
                }
                else if ($string_char == $ch) {
                    if ($escape) {
                        $escape = false;
                    }
                    else {
                        $in_string = false;
                        continue;
                    }
                }
                else {
                    $escape = false;
                }

            }
            else {
                if (preg_match("/^\s+$/i", $ch)) {
                    if (strlen($buffer) > 0) {
                        $ret[] = $buffer;
                        $buffer = "";
                        continue;
                    }
                } else if (in_array($ch, $string_chars)) {
                    if (strlen($buffer) > 0) {
                        $ret[] = $buffer;
                        $buffer = "";
                    }
                    $in_string = true;
                    $string_char = $ch;
                    continue;
                }

            }

            $buffer .= $ch;
        }

        if (strlen($buffer) > 0) {
            $ret[] = $buffer;
        }

        return $ret;
    }

    /**
     * @param $file
     * @return \API\Functions\FileInfoData
     */
    public static function parseFile($file): ?\API\Functions\FileInfoData {
        if (is_string($file)) return new \API\Functions\FileInfoData($file);
        elseif ($file instanceof \API\Functions\FileInfoData) return $file;

        return null;
    }

    public static function parse($input): \API\Functions\BaseFunction|\API\Functions\WatermarkFunction|\API\Functions\MaskFunction|null {
        //$data = explode(" ", $input);
        $data = BaseFunction::_explode($input);
        //$data = preg_split('/(?<!\\)(?:\\{2})*"[^"\\]*(?:\\.[^"\\]*)*"(*SKIP)(*F)|\h+/gmi', $input);
        $fnc_class = null;
        /** @var \API\Functions\Argument|null $last_arg */
        $last_arg = null;

        $i = 0;
        foreach ($data as $item) {
            if ($i == 0) {
                $fnc_class = match (strtolower($item)) {
                    "mask" => new \API\Functions\MaskFunction(),
                    "watermark" => new \API\Functions\WatermarkFunction(),
                    default => new \API\Functions\BaseFunction(),
                };
                $fnc_class->name = $item;

            }
            else {
                if (\str_starts_with($item, "-")) {
                    if (!empty($last_arg)) {
                        $fnc_class->arguments[$last_arg->getName()] = $last_arg;
                    }
                    $last_arg = new \API\Functions\Argument(substr($item, 1));
                }
                else {
                    $last_arg->addValue($item);
                }
            }

            $i++;
        }
        $fnc_class->arguments[$last_arg->getName()] = $last_arg;

        return $fnc_class;

    }

    public function execute($file, $target = null) {


        return $file;
    }
}