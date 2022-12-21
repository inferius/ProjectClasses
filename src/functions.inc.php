<?php

require_once("functions.shared.inc.php");
require_once("function.removeAccents.php");
require_once("function.framework.inc.php");

//require_once($config["dirs"]["web"]["libs"] . "/class.phpmailer.php");


function printOneGalleryImage($image_array, $thumbnail_index = "little_picture", $big_image_index = "big_picture") {
    echo "<figure itemprop='associatedMedia' itemscope='' itemtype='http://schema.org/ImageObject'>
            <a href='{$image_array[$big_image_index]["url"]}' itemprop='contentUrl' data-size='{$image_array[$big_image_index]["img_size"][0]}x{$image_array[$big_image_index]["img_size"][1]}'>
                <img src='{$image_array[$thumbnail_index]["url"]}' itemprop='thumbnail' alt='Image description'>
            </a>
            <figcaption itemprop='caption description' class='hidden'></figcaption>
        </figure>";
}

function register_user_by_social($json_data, $type) {
    $user_data = [
        "firstname" => $json_data["firstname"],
        "surname" => $json_data["surname"],
        "manual_create_user" => 0,
        "password" => generateRandomString(20),
        "register_from_promo" => empty($_SESSION["promo_code"]) ? "" : $_SESSION["promo_code"],
        "is_b2b" => $_SESSION[SESS_WEBTYPE] == BUSSINES_WEB ? 1 : 0,
    ];

    if (!empty($json_data["email"])) $user_data["email"] = $json_data["email"];

    $token_name = "";
    if ($type == "gl") {
        $user_data["google_id"] = $json_data["client_id"];
    }
    else if ($type == "fb") {
        $user_data["facebook_id"] = $json_data["client_id"];
    }
    else {
        return false;
    }

    $user = \API\Users::createUser($json_data["client_id"] . "|from_{$type}", $user_data, ["registered", "web", "users"]);

    $user->update("promocode", "17" . str_pad($user->getId(), 5, "0", STR_PAD_LEFT));

    \API\Users::registerLogin($user->getId());

    after_register($user);
    \API\Users::logout();
    \API\Users::registerLogin($user->getId());

    return true;
}


function login_user_by_social($user_id, $type = null, $client_id = null) {
    global $config;
    require_once($config["path"]["absolute"]["framework"]["php"] . "/users.php");

    \API\Users::registerLogin($user_id);
    $l_u = \API\Users::getLoggedUser();


    if (!empty($type) && !empty($client_id)) {
        $attrname = "";
        if ($type == "gl") {
            $attrname = "google_id";
        }
        else if ($type == "fb") {
            $attrname = "facebook_id";
        }

        $l_u->update($attrname, $client_id);
    }

}



function getUrl($url) {
    $final_url = "";
    $final_url = appendSlashToUrl($url);
    // else {
    //     $personal_url = L::pt("url_personal", "/osobni");
    //     $final_url = (startsWith($url, "/") ? $personal_url : appendSlashToUrl($personal_url)) . appendSlashToUrl($url);
    //     //return L::pt("url_virtual_asistant", "/virtualni-asistent") . $url;
    // }
    if (!startsWith($final_url, "/")) $final_url = "/" . $final_url;

    return $final_url;
}

function getEmalingVariableList(\API\Users $user) {
    global $config;
    global $setting;

    $url_top_domain = $_SESSION[SESS_LANG]["id"] == 1 ? "eu" : "cz";

    return [ 
        "user_fullname" => $user->getValue("firstname") . " " . $user->getValue("surname"), 
        "fullyear" => date("Y"), 
        "unsubscribe" => "{$config["webinfo"]["protocol"]}://{$config["webinfo"]["host"]}/unsubscribe/" . base64_encode($user->getValue("email")),
        "user_registered" => $user->getCreated(), 
        "registration_link" => "https://www.mytimi.{$url_top_domain}/promo/" . $user->getValue("promocode"), 
        "promo_code" => $user->getValue("promocode"),
        "free_credit" => $setting->getValue("credit_after_register"),
        "invite_credit" => $setting->getValue("promo_credit_sale"),
        "discount_loyalty" =>$setting->getValue("discount_loyalty"),
        "discount_register" =>$setting->getValue("after_reg_discount"),
        "discount_by_promo" =>$setting->getValue("discount_promo"),
        "recovery_url" =>"RecoveryURL",
    ];
}


function getMenuItems($grp = []) {
    global $connection;

    $sql_items = $connection->fetchAll("SELECT fsld.*, fs.* FROM _front_pages AS fs LEFT JOIN (SELECT * FROM _front_pages_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id WHERE fs.grp IN (?) ORDER BY ord", $_SESSION[SESS_LANG]["id"], $grp);

    $items = [];
    foreach ($sql_items as $item) {
        $items[$item["text_id"]] = $item;
    }

    return [
        "items" => $sql_items,
        "fetch" => $items
    ];
}

function getInnerCodes() {
    global $connection;
    $c = [];
    $data = $connection->query("SELECT fsld.*, ff.url as image_url, fs.* FROM _front_inner_text_codes AS fs LEFT JOIN (SELECT * FROM _front_files WHERE type = 'thumb') AS ff ON ff.parent_id = fs.image_id LEFT JOIN (SELECT * FROM _front_inner_text_codes_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id", $_SESSION[SESS_LANG]["id"]);

    foreach ($data as $d) {
        $c[$d["name"]] = [
            "text" => $d["text"],
            "key" => $d["name"]
        ];
    }

    return $c;
}


function getImageElement($url, $setting = null) {
    $default = [ "extensions" => ["jpg"], "default_ext" => "jpg", "alt" => "", "attrs" => []];
    if (empty($setting)) $setting = $default;

    $setting = array_merge($default, $setting);
    $alt = !empty($setting["alt"]) ? " alt='{$setting["alt"]}'" : "";

    $setting["extensions"] = [];
    if (count($setting["extensions"]) <= 1){
        $ext = empty($setting["extensions"][0]) ? $setting["default_ext"] : $setting["extensions"][0];
        return "<img src='$url.$ext'$alt />";
    }

    $r = "<picture";
    if (!empty($setting["attrs"])) {
        foreach ($setting["attrs"] as $key => $val) {
            $r .= " $key='$val'";
        }
    }
    $r .= ">";
    foreach ($setting["extensions"] as $ext) {
        $mime = getImageMime($ext);
        $r .= "<source srcset='$url.$ext' type='$mime' />";
    }
    $r .= "<img srcset='$url.{$setting["default_ext"]}'$alt />";
    //$r .= "<img srcset=''$alt />";
    $r .= "</picture>";

    return $r;
}

function getCFUser($service_id, $subservice_id = null) {
    global $connection;

    $f_u = null;

    foreach ($connection->query("SELECT fs.*, fsld.short_text as description FROM _front_cf_people AS fs LEFT JOIN (SELECT * FROM _front_cf_people_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id WHERE (fs.sub_service_id = ? AND fs.main_service_id = ?) OR fs.main_service_id = ? OR fs.default_user = 1 ORDER BY default_user ASC", $_SESSION[SESS_LANG]["id"], $subservice_id, $service_id, $service_id)
    as $p) {
        if ($subservice_id != null && $p["sub_service_id"] == $subservice_id) {
            $f_u = $p;
            break;
        }
        else if ($p["main_service_id"] == $service_id && $p["sub_service_id"] == null) {
            $f_u = $p;
        }
        else if ($p["default_user"] == 1) {
            $f_u = $p;
        }
    }

    $r = [
        "person" => $f_u,
        "person_data" => null,
        "cf_people"=> null
    ];
    if ($f_u != null) {
        $pdata = $connection->fetch("SELECT fs.*, ff.url as image_url, fsld.name, fsld.role FROM _front_cp_people AS fs LEFT JOIN (SELECT * FROM _front_files WHERE type = 'thumb') AS ff ON (ff.parent_id = fs.image_id OR ff.id = fs.image_id) LEFT JOIN (SELECT * FROM _front_cp_people_lang_data WHERE lang_id = ?) AS fsld ON fsld.parent_id = fs.id WHERE fs.id = ? LIMIT 1", $_SESSION[SESS_LANG]["id"], $f_u["user_id"]);
        $r["person_data"] = $pdata;
        $r["cf_people"] = $pdata;

        foreach ($f_u as $key => $val) {
            $r["cf_people"][$key] = $val;
        }

    }

    return $r;
}

function getPageInfo($id, $by = "text_id") {
    global $connection;
    return $connection->fetch("SELECT fsld.*, fs.* FROM _front_pages AS fs LEFT JOIN (SELECT * FROM _front_pages_lang_data WHERE lang_id = {$_SESSION[SESS_LANG]["id"]}) AS fsld ON fsld.parent_id = fs.id WHERE fs.grp in ('main_menu', 'main_menu_bus', 'main_menu_bus_2', 'new_main_menu') AND $by = ? LIMIT 1", $id);
}

function appendSlashToUrl($url) {
    if (endsWith($url, "/")) return $url;
    else return $url . "/";
}

function processMetadataByObject(\API\BaseObject $obj, ?string $url) {
    global $setting;
    if (empty($obj)) return;

    foreach (["meta_title", "meta_description", "meta_keyword"] as $meta_value) {
        if (!empty($obj->getValue($meta_value))) {
            $GLOBALS["page_info"][$meta_value] = $obj->getValue($meta_value);
        }
    }

    $title = $obj->getValue("meta_title");

    if (empty($obj->getValue("meta_title"))) {
        $GLOBALS["page_info"]["name"] = $obj->getValue("name");
        $title = $obj->getValue("name");
    }

    $url_e = "";
    if (!empty($url)) {
        $url_e = $url;

        if (!endsWith($url, "/")) $url_e = $url . "/";
        if (!startsWith($url, "/")) $url_e = "/" . $url_e;
    }

    //$GLOBALS["og_meta"] = [];

    $GLOBALS["og_meta"]["locale"] = 'cs_CZ';
    $GLOBALS["og_meta"]["site_name"] = "Algo Cloud - cloudové služby, úložiště a řešení pro firmy";
    $GLOBALS["og_meta"]["type"] = 'article';
    $GLOBALS["og_meta"]["url"] = 'https://'.$_SERVER["HTTP_HOST"] . $url_e;
    $GLOBALS["og_meta"]["title"] = $title;
    $GLOBALS["og_meta"]["description"] = $obj->getValue("meta_description");

    if (!empty($obj->getValue("intro_image"))) {
        $GLOBALS["og_meta"]['image'] = "https://" . $_SERVER["HTTP_HOST"] . "" . getFileUrl($obj->getValue("intro_image")["url"]);
    }
    else {
        $GLOBALS["og_meta"]['image'] = "https://" . $_SERVER["HTTP_HOST"] . '/images/meta/cloud-cloudove-sluzby-reseni-uloziste.jpg';
    }
}


/**
 * @param string[] $ids
 * 
 * @return mixed[]
 */
function getImagesByIds($ids) {
    global $connection;

    $files = [];
    $files_sql = $connection->query("SELECT * FROM fp_temp_files WHERE id in (?)", $ids);
    foreach ($files_sql as $file) {
        $files[$file["id"]] = $file;
    }

    return $files;

}

/**
 * @param string $attrname
 * @param mixed[] $data
 * @return string[]
 */
function getFileIdListFromData($attrname, $data) {
    return array_map(function($item) use ($attrname) {
        return $item[$attrname];
    }, $data);
}

function getFileUrl($file_url) {
    return "/temp_uploaded_data" . $file_url;
}

function predlozkyUprava($text) {
    // automaticke pridanie nezalomitelnych medzier pred spojky a predlozky - tzv. sirotkov
    // tak aby takyto znak nebol na konci riadku (a, o, v, i, pre, pri, na, do, za..)
    $old_str = array(" A ", " a ", " Bez ", " bez ", " Do ", " do ", " I ", " i ", " K ", " k ", " Krom ", " krom ", " Na ", " na ", " Nad ", " nad ", " O ", " o ", " Od ", " od ", " Po ", " po ", " Pod ", " pod ", " Pro ", " pro ", " Před ", " před ", " Při ", " při ", " S ", " s ", " Skrz ", " skrz ", " U ", " u ", " V ", " v ", " Z ", " z ", " Za ", " za ", "\r\n");
    $new_str = array(" A&#160;", " a&#160;", " Bez&#160;", " bez&#160;", " Do&#160;", " do&#160;", " I&#160;", " i&#160;", " K&#160;", " k&#160;", " Krom&#160;", " krom&#160;", " Na&#160;", " na&#160;", " Nad&#160;", " nad&#160;", " O&#160;", " o&#160;", " Od&#160;", " od&#160;", " Po&#160;", " po&#160;", " Pod&#160;", " pod&#160;", " Pro&#160;", " pro&#160;", " Před&#160;", " před&#160;", " Při&#160;", " při&#160;", " S&#160;", " s&#160;", " Skrz&#160;", " skrz&#160;", " U&#160;", " u&#160;", " V&#160;", " v&#160;", " Z&#160;", " z&#160;", " Za&#160;", " za&#160;", "");
    
    return str_replace($old_str, $new_str, $text);
}

function removeTagsBlogPreview($text) {
    return strip_tags($text, "<strong><br><p><span>");
}

function getMemCache($cache_key) {
    global $memcache;

    if (class_exists("Memcache")) {
        $r = $memcache->get($cache_key);
        if (!empty($r)) return $r;
    }

    return null;
}

function setMemCache($cache_key, $value, $expirated = null) {
    global $memcache;

    if (class_exists("Memcache")) {
        $memcache->set($cache_key, $value, null, $expirated);
    }
}

function getHtmlText($text) {
    return tidy_repair_string($text, array('show-body-only' => true));
}

function getStaticPageUrl($text_id) {
    if (!empty(getMemCache("getStaticPageUrl[$text_id]"))) return getMemCache("getStaticPageUrl[$text_id]");

    $obj = \API\BaseObject::getObjectByAttr("static_pages_data", "text_id", $text_id);
    if (empty($obj->getValue("url"))) return "/";

    if (empty($obj->getValue("url")->getValue("url"))) return "/";

    setMemCache("getStaticPageUrl[$text_id]", "/" . $obj->getValue("url")->getValue("url") . "/", strtotime("+1 day"));

    return "/" . $obj->getValue("url")->getValue("url") . "/";
}

function getCmsPageUrl($text_id) {
    if (!empty(getMemCache("getCmsPageUrl[$text_id]"))) return getMemCache("getCmsPageUrl[$text_id]");

    $obj = \API\BaseObject::getObjectByAttr("crm_pages", "text_id", $text_id);
    if (empty($obj->getValue("url"))) return "/";

    if (empty($obj->getValue("url")->getValue("url"))) return "/";

    setMemCache("getCmsPageUrl[$text_id]", "/" . $obj->getValue("url")->getValue("url") . "/", strtotime("+1 day"));

    return "/" . $obj->getValue("url")->getValue("url") . "/";
}

function is_html_text_empty($text) {
    global $config;

    if (is_null($text)) return true;

    if (!is_string($text)) {
        //if ($config["debug"]["status"]) throw new \Exception("Text is not string");
        if ($config["debug"]["status"]) {
            dump("Text is not string");
            dump($text);
        }
        return true;
    }
    return mb_strlen(preg_replace("/\s+/i", "", strip_tags($text)), 'UTF-8') === 0;
}

function getMenuItem($text_id, $submenu = "", $no_url = false) {
    global $connection;
    global $memcache;

    $lang_id = $_SESSION[SESS_LANG]["id"];

    $cache_key = "Menu:$text_id:$submenu:$lang_id";

    if (class_exists("Memcache")) {
        $r = $memcache->get($cache_key);
        if (!empty($r)) return $r;
    }

    $url = "";
    $text = "";

    $data = $connection->fetch("SELECT url_data.url as url, ml.name as name, m.text_id as text_id FROM _mct_static_pages_data AS m INNER JOIN _mct_static_pages_data_lang_data AS ml ON m.id = ml.parent_id INNER JOIN _mct_url_manager_lang_data AS url_data ON url_data.parent_id = m.url AND url_data.lang_id = ? WHERE m.text_id = ? AND ml.lang_id = ?", $lang_id, $text_id, $lang_id);

    if ($text_id == "our_doctors") {
        $url = "lekarske-obory";
        $text = "Lékařské obory";
    }

    if (!empty($data)) {
        $url = $data["url"];
        $text = $data["name"];
    }

    $data = $connection->fetch("SELECT url_data.url as url, ml.name as name, m.text_id as text_id FROM _mct_crm_pages AS m INNER JOIN _mct_crm_pages_lang_data AS ml ON m.id = ml.parent_id INNER JOIN _mct_url_manager_lang_data AS url_data ON url_data.parent_id = m.url AND url_data.lang_id = ? WHERE m.text_id = ? AND ml.lang_id = ?", $lang_id, $text_id, $lang_id);
    if (!empty($data)) {
        $url = $data["url"];
        $text = $data["name"];
    }

    $data = [
        "url" => $url,
        "text" => $text
    ];

    if (!empty($submenu)) {
        $data["submenu"] = $submenu;
        if ($no_url) $data["url"] = "";
    }

    if (class_exists("Memcache")) {
        $memcache->set($cache_key, $data, null, 86400);
    }

    return $data;
}

function getUrlLine($dir, $urls, $names) {
    return [
        "url" => [
            "cz" => $urls["cz"],
            "en" => $urls["en"]
        ],
        "name" => [
            "cz" => $names["cz"],
            "en" => $names["en"]
        ],
        "dir" => $dir
    ];
}

function getUrlLineByUrl($url_list, $url, $lang = "cz") {
    foreach ($url_list as $key => $val) {
        if ($val["url"][$lang] == $url) {
            $val["text_id"] = $key;
            return $val;
        }
    }
}

function getPackageUrl($text_id, $package, $age = null, $force_lang = false) {
    global $config;

    $real_lang = $_SESSION[SESS_LANG]["short"];
    $used_lang = $real_lang;
    if (!empty($force_lang )) $used_lang = $force_lang;

    //require(__DIR__ . "/../templates/packages/url_list.php");
    require($config["path"]["absolute"]["templates"] . "/pages/packages_data/data/url_list.php");

    $package_url_data = getMenuItem("packages");


    $url = "/{$package_url_data['url']}";
    if (!empty($url_list[$text_id])) $url .= "/{$url_list[$text_id]["url"][$used_lang]}";
    $url .= "/$package";
    if (!empty($age)) $url .= "/$age";

    return $url;
}

function getMultiAgeLinks($text_id, $package, $ages = []) {
    $r = [];
    foreach ($ages as $age) {
        $r[] = getUrlLine($age["url"]["cz"], ["cz" => getPackageUrl($text_id, $package, $age["url"]["cz"], "cz"), "en" => getPackageUrl($text_id, $package, $age["url"]["en"], "en")], [ "cz" => $age["name"]["cz"], "en" => $age["name"]["en"]]);
    }

    return $r;
}


function replaceCode($txt) {
    $code = [
        "search" => ["[icon_place]"],
        "replace" => [
            \API\Frontend\PageTemplate::get("global/shared/icon_plate")
        ]
    ];

    $i_text = $txt;

    $i_text = str_replace($code["search"], $code["replace"], $i_text);
    $i_text = replaceParamsInText($i_text);

    return $i_text;
}