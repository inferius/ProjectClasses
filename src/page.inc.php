<?php

namespace API\Frontend;

require_once($config["path"]["absolute"]["framework"]["php"]  . "/Exceptions/StopDrawingExceptions.inc.php");
class PageTemplate
{
    public static $page_variant = "web";

    public static $js_files = [];
    public static $css_files = [];
    public static $css_mob_files = [];
    public static $css_tab_files = [];

    public static $print_end_script = [];
    public static $print_preloaded_css = [];

    public static $external_file_loaded_js = [];
    public static $external_file_loaded_css = [];

    private static $once_load_temlate = [];

    private static $full_load_data = [ "#file" => [ "css" => "", "js" => "" ] ];

    public static function loadExternalJS($filelist = []) {
        $endArr = null;
        if (is_string($filelist)) {
            $endArr = [ $filelist ];
        }
        else if (!is_array($filelist)) return;

        foreach ($endArr as $ai) {
            if (is_string($ai)) {
                if (in_array($ai, PageTemplate::$external_file_loaded_js)) continue;

                PageTemplate::$external_file_loaded_js[] = $ai;
                echo "<script src='{$ai}'></script>";
            }
        }
    }

    public static function loadExternalCSS($filelist = []) {
        $endArr = null;
        if (is_string($filelist)) {
            $endArr = [ $filelist ];
        }
        else if (!is_array($filelist)) return;

        foreach ($endArr as $ai) {
            if (is_string($ai)) {
                if (in_array($ai, PageTemplate::$external_file_loaded_css)) continue;

                PageTemplate::$external_file_loaded_css[] = $ai;
                echo "<link href='{$ai}' rel='stylesheet' />";
            }
        }
    }


    /**
     * Načte pouze javascriptove soubory nebo styly pro danou stranku, bez toho aniz by vykreslila jeji obsah
     */
    public static function load_css_js($page_name, $load_param = [ "css" => true, "js" => true, "async" => true ]) {
        if (!empty($load_param["css"])) self::load_css_internal($page_name, null);
        if (!empty($load_param["js"])) self::load_js_internal($page_name, empty($load_param["async"]) ? true : $load_param["async"],  null);
    }

    private static function add_full_load_data($page_name, $type, $path) {
        if (empty(self::$full_load_data[$page_name])) self::$full_load_data[$page_name] = [ "name" => $page_name, "css" => [], "js" => []];

        self::$full_load_data[$page_name][$type][] = $path;
        self::$full_load_data["#file"][$type] .= $page_name;
    }

    private static function load_css_internal($page_name, $template_data = null) {
        global $config;
        if (empty($template_data)) $template_data = self::getTemplateData($page_name);

        $old_css_path = $template_data["dirs"]["root"] . "/page.css";

        $css_path = $template_data["dirs"]["root"] . "/compile/page.css";
        $css_min_path = $template_data["dirs"]["root"] . "/compile/page.min.css";
        $less_path = $template_data["dirs"]["root"] . "/page.less";

        $css_mobile_path = $template_data["dirs"]["root"] . "/compile/page.mobile.css";
        $css_mobile_min_path = $template_data["dirs"]["root"] . "/compile/page.mobile.min.css";
        $less_mobile_path = $template_data["dirs"]["root"] . "/page.mobile.less";

        $css_tablet_path = $template_data["dirs"]["root"] . "/compile/page.tablet.css";
        $css_tablet_min_path = $template_data["dirs"]["root"] . "/compile/page.tablet.min.css";
        $less_tablet_path = $template_data["dirs"]["root"] . "/page.tablet.less";


        if (is_file($css_path)) {
            if (!in_array($css_path, self::$css_files)) {
                if (!$_SESSION["is_ie"]) {
                    //if (empty($config["debug"]["status"])) self::$print_preloaded_css[]= "appendPreloadStyleElement('{$template_data['dirs']['relative']['root']}/compile/page.min.css');";
                    //else self::$print_preloaded_css[]= "appendPreloadStyleElement('{$template_data['dirs']['relative']['root']}/compile/page.css');";
                    if (empty($config["debug"]["status"])) self::$print_end_script[]= "<link href='{$template_data['dirs']['relative']['root']}/compile/page.min.css' rel='stylesheet' />";
                    else self::$print_end_script[]=  "<link href='{$template_data['dirs']['relative']['root']}/compile/page.css' rel='stylesheet' />";
                }
                else {
                    if (empty($config["debug"]["status"])) self::$print_end_script[]= "<link href='{$template_data['dirs']['relative']['root']}/compile/page.min.css' rel='stylesheet' />";
                    else self::$print_end_script[]=  "<link href='{$template_data['dirs']['relative']['root']}/compile/page.css' rel='stylesheet' />";
                }

                self::$css_files[] = $css_path;
                self::add_full_load_data($page_name, "css", $css_min_path);
            }
        }
        else if (is_file($old_css_path)) {
            if (!in_array($old_css_path, self::$css_files)) {
                self::$print_end_script[]= "<link href='{$template_data['dirs']['relative']['root']}/page.css' rel='stylesheet' />";

                self::$css_files[] = $old_css_path;
                self::add_full_load_data($page_name, "css", $old_css_path);
            }
        }

        if (is_file($css_tablet_path)) {
            if (!in_array($css_tablet_path, self::$css_tab_files)) {
                if (empty($config["debug"]["status"])) self::$print_end_script[]= "<link href='{$template_data['dirs']['relative']['root']}/compile/page.tablet.min.css' media='screen and (min-width: 769px) and (max-width:1200px)' rel='stylesheet' />";
                else self::$print_end_script[]=  "<link href='{$template_data['dirs']['relative']['root']}/compile/page.tablet.css' media='screen and (min-width: 769px) and (max-width:1200px)' rel='stylesheet' />";

                self::$css_tab_files[] = $css_tablet_path;
                self::add_full_load_data($page_name, "css", $css_tablet_min_path);
            }
        }


        if (is_file($css_mobile_path)) {
            if (!in_array($css_mobile_path, self::$css_mob_files)) {
                if (empty($config["debug"]["status"])) self::$print_end_script[]= "<link href='{$template_data['dirs']['relative']['root']}/compile/page.mobile.min.css' media='screen and (max-width:768px)' rel='stylesheet' />";
                else self::$print_end_script[]=  "<link href='{$template_data['dirs']['relative']['root']}/compile/page.mobile.css' media='screen and (max-width:768px)' rel='stylesheet' />";

                self::$css_mob_files[] = $css_mobile_path;
                self::add_full_load_data($page_name, "css", $css_mobile_min_path);
            }
        }

    }

    private static function load_js_internal($page_name, $async = true, $template_data = null) {
        global $config;
        if (empty($template_data)) $template_data = self::getTemplateData($page_name);
        $js_path = $template_data["dirs"]["root"] . "/page.js";
        $ts_path = $template_data["dirs"]["root"] . "/page.ts";
        $js_es5_path = $template_data["dirs"]["root"] . "/compile/page.es5.js";
        $js_es5min_path = $template_data["dirs"]["root"] . "/compile/page.es5.min.js";

        $js_es2017_path = $template_data["dirs"]["root"] . "/compile/page.es2017.js";
        $js_es2017min_path = $template_data["dirs"]["root"] . "/compile/page.es2017.min.js";

        if (is_file($js_path) || is_file($ts_path)) {
            if (!in_array($js_path, self::$js_files)) {
                if ($_SESSION["is_ie"]) {
                    if (is_file($js_es5min_path) && empty($config["debug"]["status"])) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es5.min.js'></script>";
                    else if (is_file($js_es5_path)) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es5.js'></script>";
                    else self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/page.js'></script>";
                    self::add_full_load_data($page_name, "js", $js_es5min_path);
                }
                else {
                    $param_text = [
                        "async" => "async defer"
                    ];
                    if (!$async) $param_text["async"] = "";
                    if (!empty($config["debug"]["status"])) {
                        if (is_file($ts_path)) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es2017.js' {$param_text["async"]}></script>";
                        else self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/page.js' {$param_text["async"]}></script>";
                    }
                    else {
                        if (is_file($js_es2017min_path) && empty($config["debug"]["status"])) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es2017.min.js' {$param_text["async"]}></script>";
                        else if (is_file($js_es2017_path)) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es2017.js' {$param_text["async"]}></script>";
                    }
                    self::add_full_load_data($page_name, "js", $js_es2017min_path);
                }
                //echo "<script src='{$template_data['dirs']['relative']['root']}/page.js'></script>";
                self::$js_files[] = $js_path;
            }
        }
    }

    private static function getTemplateData($page_name) {
        global $config;

        return array(
            "dirs" => [
                "web" => $config["path"]["absolute"]["root"] ,
                "root" => $config["path"]["absolute"]["templates"] . "/{$page_name}",
                "relative" => [
                    "root" => $config["path"]["relative"]["templates"] . "/{$page_name}",
                ],
            ],
        );
    }

    /**
     * Zobrazí stránku podle zadané šablony
     *
     */
    public static function show($page_name, $params = [])
    {
        self::executeTemplate($page_name, false, $params);
    }

    /*
    * Zobrazi pouze jednou pri ostatnich pokusech nacist sablonu ignoruje
    */
    public static function showOnce($page_name, $params = [])
    {
        if (in_array($page_name, self::$once_load_temlate)) return;
        self::$once_load_temlate[] = $page_name;

        self::executeTemplate($page_name, false, $params);
    }

    public static function get($page_name, $params = []) {
        return self::executeTemplate($page_name, true, $params);
    }

    private static function executeTemplate($page_name, $as_string = false, $params = []) {
        global $config;
        global $global_data;
        global $setting;
        global $presenter;
        global $connection;

        $async = true;

        if (isset($params["async"]))  {
            $async = $params["async"];
            unset($params["async"]);
        }
        //global $template_params;

        $template_data = self::getTemplateData($page_name);

        $basic_tpl_params = [
            "config" => $config,
            "tpl_params" => $template_data,
            "global" => $global_data,
            "setting" => $setting,
            "user" => \API\Users::getLoggedUser()
        ];


        if (!empty($params) && is_array($params)) {
            if (empty($template_params)) $template_params = $params;
            $template_params = array_merge($template_params, $params);
        }

        if (isset($template_params)) {$template_params = array_merge($template_params, $basic_tpl_params);} else {
            $template_params = $basic_tpl_params;
        }


        $php_path = $template_data["dirs"]["root"] . "/page.php";
        //try {
            if (is_file($php_path)) {
                include_once $php_path;
                $vars = get_defined_vars();
            }
        //}
        //catch(\API\Frontend\StopDrawingExceptions $e) {
        //    return;
        //}



        $tpl_path = $template_data["dirs"]["root"] . "/page.latte";


        /*if (is_file($less_path)) {
        $config["less"]["instance"]->checkedCompile($less_path, $css_path);
        }*/


        self::load_css_internal($page_name, $template_data);
        self::load_js_internal($page_name, $async, $template_data);

        if (is_file($tpl_path)) {
            if ($as_string) return $presenter->renderToString($tpl_path, $template_params);

            $presenter->render($tpl_path, $template_params);
        } else {
            //if (!empty($config["debug"]["status"])) {
            //throw new \Exception("Template '{$page_name}' not found!");
            //}
        }

        return "";
    }

    public static function createOptiScriptLoad() {
        global $config;

        $file_name_css = self::$full_load_data["#file"]["css"];
        $file_name_js = self::$full_load_data["#file"]["js"];

        //$f_css_hash = hash("SHA512", $file_name_css) . ".css";
        //$f_js_hash = hash("SHA512", $file_name_js) . ".js";

        $f_css_hash = sprintf('%u', crc32($file_name_css)) . ".css";
        $f_js_hash = sprintf('%u', crc32($file_name_js)). ".js";
    
        $path_dir = $config["path"]["absolute"]["temp"] . "/CSS_JS_Cache";
        $path_url = $config["path"]["relative"]["temp"] . "/CSS_JS_Cache";

        $css_path = $path_dir . "/" . $f_css_hash;
        $js_path = $path_dir . "/" . $f_js_hash;

        $css_data = "";
        $js_data = "";
        if (!file_exists($css_path) || $config["debug"]["status"]) {
        
            foreach (self::$full_load_data as $key => $val) {
                
                if (!empty($val["css"]) && is_array($val["css"])){
                    foreach ($val["css"] as $css_item_file) {
                        $css_data .= file_get_contents($css_item_file);
                    }
                }
                if (!empty($val["js"]) && is_array($val["js"])) {
                    foreach ($val["js"] as $js_item_file) {
                        $js_data.= file_get_contents($js_item_file);
                    }
                }
            }
            file_put_contents($css_path, $css_data);
            file_put_contents($js_path, $js_data);
        }

        return [
            "css"=> $css_path,
            "js" => $js_path,
            "css_url" => $path_url . "/". $f_css_hash,
            "js_url" => $path_url . "/". $f_js_hash,
        ];
    }
}
