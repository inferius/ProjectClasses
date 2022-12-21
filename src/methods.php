<?php
namespace API;

require_once($config["path"]["absolute"]["framework"]["php"] . "/base.inc.php");

class UserMethod {
    public static function get_method($method_name) {
        return UserMethod::replace_method_in_code(UserMethod::get_plain_method($method_name));
    }

    private static function get_plain_method($method_name) {
        global $connection;
        $method_path = __DIR__ . "/Methods/{$method_name}.php";
        $method = $connection->fetch("SELECT * FROM methods WHERE name = '$method_name'");

        if (empty($method)) {
            if (file_exists($method_path)) {
                return file_get_contents($method_path);
            }
            throw new \API\Exceptions\UserMethodNotFoundException($method_name, "Method '$method_name' not found");
        }

        return $method["content"];
    }

    public static function run_method($method_name, array $arguments) {
        $method = UserMethod::get_method($method_name);

        foreach ($arguments as $key => $val) {
            $$key = $val;
        }

        return eval($method);
    }

    public static function replace_method_in_code($code) {
        $res = preg_match_all("/API.Method::([A-Za-z_\-0-9]+);/", $code, $matches);

        if (empty($res)) return $code;

        for ($i = 0; $i < $res; $i++) {
            $meth = UserMethod::get_plain_method($matches[1][$i]);
            $code = str_replace($matches[0][$i], $meth, $code);
        }

        return UserMethod::replace_method_in_code($code);
    }

    public static function run_code($code, array $arguments = []) {
        foreach ($arguments as $key => $val) {
            $$key = $val;
        }

        //$n_code = UserMethod::replace_method_in_code($code);

        return eval(UserMethod::replace_method_in_code($code));
    }
}