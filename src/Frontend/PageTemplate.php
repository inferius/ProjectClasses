<?php

namespace API\Frontend;

use API\Configurator;

class PageTemplate
{
    /**
     * Nastavení zda použít proměnné z PHP souboru k šabloně přímo nebo je nuceně přidávat do $template_params proměnné
     * @var bool
     */
    public static $useInTemplatePhpVars = false;
    public static $js_files = [];
    public static $css_files = [];
    public static $print_end_script = [];

    public static $external_file_loaded_js = [];
    public static $external_file_loaded_css = [];

    private static $once_load_template = [];

    private static $full_load_data = [ "#file" => [ "css" => "", "js" => "" ] ];
    private static $global_vars = [];

    private static $template_configuration = [];

    /**
     * Načte externí JS soubor/y
     * @param string|string[] $filelist Seznam souborů
     * @return void
     */
    public static function loadExternalJS($filelist = []): void {
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

    /**
     * Načte externí CSS soubor/y
     * @param string|string[] $filelist Seznam souborů
     * @return void
     */
    public static function loadExternalCSS($filelist = []): void {
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
     * Nastavní konfiguraci pro šablony
     * @param $config array Konfigurační sada
     * @example
     * [
     * "dirs" => [
     *    "web" => \API\Configurator::$config["path"]["absolute"]["root"] ,
     *    "root" => \API\Configurator::$config["path"]["absolute"]["templates"],
     *    "relative" => [
     *      "root" => \API\Configurator::$config["path"]["relative"]["templates"],
     *    ],
     *    ],
     * ];
     * @return void
     */
    public static function configure($config): void {
        self::$template_configuration = $config;
    }

    /**
     * Přídá novou přoměnnou do globálních, které budou dostupné v každé šabloně
     * @param string $name Název proměnné použítý v šabloně
     * @param mixed|callable $var
     * @return void
     */
    public static function addGlobalVar(string $name, $var): void {
        self::$global_vars[$name] = $var;
    }

    /**
     * Načte pouze JS a CSS soubory pro danou stranku, šablona a PHP soubor se v tomto případě nezpracovávájí
     *
     * @deprecated use loadCssJs instead
     */
    public static function load_css_js($page_name, $load_param = [ "css" => true, "js" => true, "async" => true ]) {
        return self::loadCssJs($page_name, $load_param);
    }

    /**
     * Načte pouze JS a CSS soubory pro danou stranku, šablona a PHP soubor se v tomto případě nezpracovávájí
     * @param string $page_name Název šablony
     * @param array $load_param Inicializační parametry [ "css" => true, "js" => true, "async" => true ]
     */
    public static function loadCssJs(string $page_name, array $load_param = [ "css" => true, "js" => true, "async" => true ]) {
        if (!empty($load_param["css"])) self::load_css_internal($page_name, null, empty($load_param["async"]) ? true : $load_param["async"]);
        if (!empty($load_param["js"])) self::load_js_internal($page_name, empty($load_param["async"]) ? true : $load_param["async"],  null);
    }

    private static function add_full_load_data($page_name, $type, $path) {
        if (empty(self::$full_load_data[$page_name])) self::$full_load_data[$page_name] = [ "name" => $page_name, "css" => [], "js" => []];

        self::$full_load_data[$page_name][$type][] = $path;
        self::$full_load_data["#file"][$type] .= $page_name;
    }

    private static function load_css_internal($page_name, $template_data = null, $async = false) {
        
        if (empty($template_data)) $template_data = self::getTemplateData($page_name);

        $old_css_path = $template_data["dirs"]["root"] . "/page.css";

        $css_path = $template_data["dirs"]["root"] . "/compile/page.css";
        $css_min_path = $template_data["dirs"]["root"] . "/compile/page.min.css";
        $less_path = $template_data["dirs"]["root"] . "/page.less";

        $rel_attr = 'rel="stylesheet"';
        if ($async) {
            $rel_attr = 'rel="preload" as="style" onload="this.rel=\'stylesheet\'"';
        }

        $getFile = function ($fileName) use ($rel_attr) {
            return  "<link href='$fileName' $rel_attr />";
        };


        if (is_file($css_path)) {
            if (!in_array($css_path, self::$css_files)) {
                if (empty(\API\Configurator::$config["debug"]["status"])) self::$print_end_script[]= $getFile("{$template_data['dirs']['relative']['root']}/compile/page.min.css");
                else self::$print_end_script[] = $getFile("{$template_data['dirs']['relative']['root']}/compile/page.css");


                self::$css_files[] = $css_path;
                self::add_full_load_data($page_name, "css", $css_min_path);
            }
        }
        else if (is_file($old_css_path)) {
            if (!in_array($old_css_path, self::$css_files)) {
                self::$print_end_script[]= $getFile("{$template_data['dirs']['relative']['root']}/page.css");

                self::$css_files[] = $old_css_path;
                self::add_full_load_data($page_name, "css", $old_css_path);
            }
        }

    }

    private static function load_js_internal($page_name, $async = true, $template_data = null) {
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
                    if (is_file($js_es5min_path) && empty(\API\Configurator::$config["debug"]["status"])) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es5.min.js'></script>";
                    else if (is_file($js_es5_path)) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es5.js'></script>";
                    else self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/page.js'></script>";
                    self::add_full_load_data($page_name, "js", $js_es5min_path);
                }
                else {
                    $param_text = [
                        "async" => "async defer"
                    ];
                    if (!$async) $param_text["async"] = "";
                    if (!empty(\API\Configurator::$config["debug"]["status"])) {
                        if (is_file($ts_path)) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es2017.js' {$param_text["async"]}></script>";
                        else self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/page.js' {$param_text["async"]}></script>";
                    }
                    else {
                        if (is_file($js_es2017min_path) && empty(\API\Configurator::$config["debug"]["status"])) self::$print_end_script[]= "<script src='{$template_data['dirs']['relative']['root']}/compile/page.es2017.min.js' {$param_text["async"]}></script>";
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
        return [
            "dirs" => [
                "web" => self::$template_configuration["dirs"]["web"],
                "root" => self::$template_configuration["dirs"]["root"] . "/{$page_name}",
                "relative" => [
                    "root" => self::$template_configuration["dirs"]["relative"]["root"] . "/{$page_name}",
                ],
            ],
        ];
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
        if (in_array($page_name, self::$once_load_template)) return;
        self::$once_load_template[] = $page_name;

        self::executeTemplate($page_name, false, $params);
    }

    public static function get($page_name, $params = []) {
        return self::executeTemplate($page_name, true, $params);
    }

    private static function executeTemplate($page_name, $as_string = false, $params = []) {
        $async = true;

        if (isset($params["async"]))  {
            $async = $params["async"];
            unset($params["async"]);
        }

        $template_data = self::getTemplateData($page_name);

        $basic_tpl_params = [
            "tpl_params" => $template_data,
        ];

        foreach (self::$global_vars as $key => $val) {
            if (is_callable($val)) {
                $basic_tpl_params[$key] = $val();
            }
            else{
                $basic_tpl_params[$key] = $val;
            }
        }


        if (!empty($params) && is_array($params)) {
            if (empty($template_params)) $template_params = $params;
            $template_params = array_merge($template_params, $params);
        }

        if (isset($template_params)) {$template_params = array_merge($template_params, $basic_tpl_params);} else {
            $template_params = $basic_tpl_params;
        }


        $php_path = $template_data["dirs"]["root"] . "/page.php";
        if (is_file($php_path)) {
            // Inicializace globalnich promennych
            foreach (self::$global_vars as $key => $val) {
                if (is_callable($val)) {
                    $$key = $val();
                }
                else{
                    $$key = $val;
                }
            }

            include_once $php_path;
            if (self::$useInTemplatePhpVars) {
                $vars = get_defined_vars();
                $template_params = array_merge($vars, $template_params);
            }
        }




        $tpl_path = $template_data["dirs"]["root"] . "/page.latte";

        self::load_css_internal($page_name, $template_data);
        self::load_js_internal($page_name, $async, $template_data);

        if (is_file($tpl_path)) {
            if ($as_string) {
                return Configurator::$presenter->renderToString($tpl_path, $template_params);
            }

            Configurator::$presenter->render($tpl_path, $template_params);
        }

        return "";
    }

    public static function createOptiScriptLoad() {
        

        $file_name_css = self::$full_load_data["#file"]["css"];
        $file_name_js = self::$full_load_data["#file"]["js"];

        $f_css_hash = sprintf('%u', crc32($file_name_css)) . ".css";
        $f_js_hash = sprintf('%u', crc32($file_name_js)). ".js";
    
        $path_dir = \API\Configurator::$config["path"]["absolute"]["temp"] . "/CSS_JS_Cache";
        $path_url = \API\Configurator::$config["path"]["relative"]["temp"] . "/CSS_JS_Cache";

        $css_path = $path_dir . "/" . $f_css_hash;
        $js_path = $path_dir . "/" . $f_js_hash;

        $css_data = "";
        $js_data = "";
        if (!file_exists($css_path) || \API\Configurator::$config["debug"]["status"]) {
        
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
