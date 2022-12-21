<?php


function parse_main_url($url_input, $parameters_parsing_char = "/", $values_parsing_char = "-")
{
    $a_parameters_from_url = [];
    $get_var = explode("?", $url_input);
    $url_input = $get_var[0];
    if (!empty($get_var[1])) {
        $vs = explode("&", $get_var[1]);
        foreach ($vs as $g) {
            $g_v = explode("=", $g);
            if (!empty($g_v[0])) {
                $_GET[$g_v[0]] = null;
                if (!empty($g_v[1])) {
                    $_GET[$g_v[0]] = $g_v[1];
                }

            }
        }
    }
    $url_input = strip_tags(htmlspecialchars($url_input));

    if (!empty($url_input)) {
        /**
         * @var array   $a_parameters_for_parsing    pomocna promenna pole obsahujici polozky, ktere byly oddelene pomoci "/"
         */
        $a_parameters_for_parsing = array();
// rozparsovani do pole po polozkach "/"
        $a_parameters_for_parsing = explode($parameters_parsing_char, $url_input);

// zjisteni, zda prvni parametr ma hodnotu pro vyber grafiky, prubezne se tu budou nastavovat pravidla, co smi, co nesmi
        if ($a_parameters_for_parsing[0] != "" || $a_parameters_for_parsing[0] != null) {

// priradeni nazvu serveru, napr edis.upol.cz
            $a_parameters_from_url["server_name"] = $a_parameters_for_parsing[0];

            if ($_SERVER["SERVER_PORT"] == 80) {
                $a_parameters_from_url["url"] = "http://" . $a_parameters_for_parsing[0];
                $a_parameters_from_url["http_prefix"] = "http://";
            } else if ($_SERVER["SERVER_PORT"] == 443) {
                $a_parameters_from_url["url"] = "https://" . $a_parameters_for_parsing[0];
                $a_parameters_from_url["http_prefix"] = "https://";
            } else {
                $a_parameters_from_url["url"] = "http://" . $a_parameters_for_parsing[0];
                $a_parameters_from_url["http_prefix"] = "http://";
            }
//rozdeleni server name na jednotlive casti
            $a_domain_parse = explode(".", $a_parameters_from_url["server_name"]);

// rozlisi se, zda jde o domenu 2. nebo 3. radu a priradi podle toho do pole
            switch (count($a_domain_parse)) {
                case 3:{
                        $a_parameters_from_url["server_name_tld"] = '.' . $a_domain_parse[2];
                        $a_parameters_from_url["server_name_domain"] = $a_domain_parse[1];
                        $a_parameters_from_url["server_name_subdomain"] = $a_domain_parse[0];
                    }
                    break;

                case 2:{
                        $a_parameters_from_url["server_name_tld"] = '.' . $a_domain_parse[1];
                        $a_parameters_from_url["server_name_domain"] = $a_domain_parse[0];
                    }
                    break;

                default:{
                        $a_parameters_from_url["server_name_domain"] = $a_domain_parse[0];
                    }
                    break;
            }

// tuto polozku smaze, neni potreba, pole se posune
            array_splice($a_parameters_for_parsing, 0, 1);

// prirazeni varianty grafiky - kdyz obsahuje "-", tak je spatne zadany format adresy, zbytek parsovani se neprovede
            // napr z edis.upol.cz/cmtf/text.html prebere "cmtf"
            if (count($a_parameters_for_parsing)) {
                $a_parameters_from_url["web_variants"] = [];
                $a_parameters_for_parsing2 = [];
                foreach ($a_parameters_for_parsing as $apfp) {
                    if (!empty($apfp) && substr_count($apfp, $values_parsing_char) != 0) {
                        $a_parameters_for_parsing2[] = $apfp;
                        continue;
                    }
// pokud prvni parametr, ktery urcuje grafiku nebude definovan, nebo je uz ve tvaru parametr=hodnota, vypln web_variant = default
                    if ($apfp == "" || ($apfp != "" && substr_count($apfp, $values_parsing_char) != 0)) {
                        //$a_parameters_from_url["web_variants"][] = "default";
                    }

                    // jinak preber prvni parametr jako urcujici pro vykresleni dane webove grafiky, smaze se prvni polozka pole, protoze uz neni potreba
                    else {
                        $apfp = urldecode($apfp);
                        // pokud je klicove slovo v poli subvariant, napln subvariantu, jinak napln variantu
                        $a_parameters_from_url["web_variants"][] = $apfp;
                        $a_parameters_from_url["url"] .= "/" . $apfp;
                        //array_splice($a_parameters_for_parsing, 0, 1);
                    }
                }
                $parameter_for_parsing = $a_parameters_for_parsing2;
                $a_parameters_from_url["containers"] = array();

// projiti celeho zbytku pole parametru a doparsovani
                foreach ($a_parameters_for_parsing as $parameter_for_parsing) {

// zjisti, zda dany parametr neobsahuje suffix s nazvem souboru (balast-soubor.html)

// projiti pole jednoho parametru a rozdeleni na parametr a hodnotu
                    $a_values_for_parsing = array();
                    $a_values_for_parsing = explode($values_parsing_char, $parameter_for_parsing, 2);
// vlozeni parametru a hodnoty do vystupniho pole, pokud byl format "parametr-hodnota"
                    if (count($a_values_for_parsing) == 2) {
                        $a_parameters_from_url["containers"][$a_values_for_parsing[0]] = $a_values_for_parsing[1];
                    }
                }
                $a_parameters_from_url["error"] = false;
                $a_parameters_from_url["error_description"]["en"] = null;
            } else {
                $a_parameters_from_url["error"] = true;
                $a_parameters_from_url["error_description"]["en"] = "bad url format";
            }
        }
        return $a_parameters_from_url;
    }
}

/**
 * Vrátí nastavenou instanci PHPMaileru z aktualnim nastavením
 */
function getMailer(): \PHPMailer\PHPMailer\PHPMailer {
    global $config;

    $emailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $emailer->CharSet = empty($config["email"]["charset"]) ? 'UTF-8' : $config["email"]["charset"];

    if ($config["email"]["type"] == "mail") {
        $emailer->isMail();
    }
    else if ($config["email"]["type"] == "qmail") {
        $emailer->isQmail();
    }
    else if ($config["email"]["type"] == "sendmail") {
        $emailer->isSendmail();
    }
    else if ($config["email"]["type"] == "smtp") {
        $emailer->isSMTP();

        $emailer->SMTPDebug = 0;  // debugging: 1 = errors and messages, 2 = messages only
        if (!empty($config["email"]["smtp"]["server"]["secure"]))
            $emailer->SMTPSecure = $config["email"]["smtp"]["server"]["secure"];

        $emailer->Host = $config["email"]["smtp"]["server"]["address"];
        $emailer->Port = $config["email"]["smtp"]["server"]["port"];

        if (!empty($config["email"]["smtp"]["auth"]["enabled"])) {
            $emailer->SMTPAuth = true;  // authentication enabled
            $emailer->Username = $config["email"]["smtp"]["auth"]["username"];
            $emailer->Password = $config["email"]["smtp"]["auth"]["password"];
        }
        else {
            $emailer->SMTPAuth = false;
        }
    }

    return $emailer;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function getImageMime($ext) {
    $arr = [
        'avif' => 'image/avif',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jfif' => 'image/jpeg',

        'heif' => 'image/heif',
        'heic' => 'image/heic',

        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml'
    ];

    return $arr[$ext];

}

function toUrl($value) {
    $value = trim($value);
    if (class_exists("Transliterator")) {
        $transliterator = \Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', \Transliterator::FORWARD);
        $value = $transliterator->transliterate($value);
    }
    else {
        $value = remove_accents($value);
    }
    $value = iconv("UTF-8", "ASCII//TRANSLIT", $value);
    $value = preg_replace("/\s+/", "-", $value);
    $value = preg_replace("/[^A-Za-z0-9_-]/", "", $value);

    $value = strtolower(strtr(stripslashes($value), "'", '-'));

    return $value;
}

function debug_dump(...$args) {
    global $config;

    if ($config["debug"]["status"]) {
        dump($args);
    }
}

function debug_dumpe(...$args) {
    global $config;

    if ($config["debug"]["status"]) {
        dumpe($args);
    }
}

function getUploadedImg($id, $alt = "", $img_config = []) {
    global $connection;
    global $config;

    list("class_name" => $class_names, "width" => $width) = $img_config;

    $key = "uploaded_file_picture_html:$id";

    if (!empty(getMemCache($key))) return getMemCache($key);

    $file = $connection->fetch("SELECT * FROM `fp_final_files` WHERE id = ?", $id);
    $default = "<img". (!empty($class_names) ? " class='$class_names'" : "") ." src='{$config["path"]["relative"]["uploaded"]}{$file["relative_path"]}' alt='$alt' loading='lazy' />";

    if (empty($file["format_info"])) {
        return $default;
    }

    $finfo = json_decode($file["format_info"], true);

    if (empty($finfo) || count($finfo["supported_width"]) == 0) {
        return $default;
    }

    $srcset = [];

    $swidth_list = $finfo["supported_width"];
    asort($swidth_list, SORT_NUMERIC);
    $sizes = [];
    $max_w = min($swidth_list);

    if (empty($width)) {
        foreach ($swidth_list as $w) {
            $w_m = intval($w * 1.55);
            //$sizes[] = $w == $max_w ? "{$w}px" : "(min-width: {$w}px) {$w_m}px";
            $sizes[] = "(min-width: {$w_m}px) {$w}px";
        }
        $sizes = array_reverse($sizes);
        $sizes[] = "100vw";
    }
    else {
        $sizes[] = $width;
    }

    foreach ($finfo["created_files"]["formats"] as $key => $val) {
        ksort($val, SORT_NUMERIC);
        $srcset[$key] = [];
        foreach ($val as $img_size => $one_info) {
            $img_size = intval($img_size * 0.9);
            $srcset[$key][] = $config["path"]["relative"]["uploaded"] . $one_info["relative"] . " {$img_size}w";
        }
    }

    $sources = [];

    foreach ($finfo["supported_formats"] as $f_format) {
        $sources[] = "<source type='".(getImageMime($f_format))."' sizes='" . (join(", ", $sizes)) . "' srcset='". (join(",", $srcset[$f_format])) ."' />";
    }

    $p = "<picture>".(join("", $sources))."<img". (!empty($class_names) ? " class='$class_names'" : "") ." src='{$config["path"]["relative"]["uploaded"]}{$file["relative_path"]}' alt='$alt' loading='lazy' /></picture>";
    setMemCache($key, $p, strtotime("+5 days"));

    return $p;
}

function getUpladedImageLatte($id, $alt = "", $config = []) {
    return new Latte\Runtime\Html(getUploadedImg($id, $alt, $config));
}

function getImageFromStaticImages($text_id, $config = [], $use_loc = false, $alt = null) {
    global $connection;

    $use_alt = $alt;

    $img = $connection->fetch("SELECT * FROM _mct_file_use_on_web WHERE text_id = ?", $text_id);
    $img_loc = $connection->fetch("SELECT * FROM _mct_file_use_on_web_lang_data WHERE parent_id = ? AND lang_id = ?", $img["id"], $_SESSION[SESS_LANG]["id"]);

    if (empty($use_alt)) {
        if (!empty($img_loc["alt"])) $use_alt = $img_loc["alt"];
    }

    if (empty($img)) return null;

    if ($use_loc) {
        return getUpladedImageLatte($img_loc["file_loc"], $use_alt, $config);
    }

    return getUpladedImageLatte($img["file"], $use_alt, $config);
}