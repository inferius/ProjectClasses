<?php
namespace API;

require_once("base.inc.php");
require_once("methods.php");
require_once("AttrTypes.php");

class Users {
    /** @var int */
    protected $id;
    /** @var \Nette\Database\IRow */
    protected $user;

    protected $groups = [];
    protected $group_id_list = [];
    protected $group_text_id_list = [];

    
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->user->name; }
    public function getCreated() { return $this->user->created; }

    function __construct(int $user_id) {
        global $context;
        global $connection;

        $this->user = $connection->fetch("SELECT * FROM users_data WHERE id = ?", $user_id);

        if ($this->user == null) {
            throw new \API\Exceptions\UserNotFoundException("User not found");
        }

        $groups_sql_data = $connection->fetchAll("SELECT pg.* FROM perm_groups AS pg INNER JOIN u_g ON u_g.group_id = pg.id WHERE user_id = ?", $this->user->id);
        foreach ($groups_sql_data as $gsd) {
            $this->groups[$gsd["text_id"]] = $gsd;
            $this->group_id_list[] = $gsd["id"];
            $this->group_text_id_list[] = $gsd["text_id"];
        }

        $this->id = $this->user->id;
        $this->name = $this->user->name;
        

        if (!empty($this->opti_data)) $this->created = strtotime($this->opti_data["created"]);

        if (empty($this->user->is_allowed)) {
            throw new \API\Exceptions\UserOperationNoAllowed("This user has been blocked");
        }

        if (!empty($this->user->to_delete)) {
            throw new \API\Exceptions\UserOperationNoAllowed("User has been deleted");
        }

    }


    public static function loadByToken(string $user_logged_token) {
        global $connection;

        $user = $connection->fetch("SELECT * FROM user_tokens WHERE token = ? AND expiration > now() AND valid_from < now()", $user_logged_token);

        if ($user == null) throw new \API\Exceptions\UserNotFoundException("Token is not valid");

        return new Users($user["usr_grp_id"]);
    }

    private static function registerUserLogin($id, $permanent = false, $is_admin_login = false) {
        global $connection;
        $user = new Users($id);

        $token = \API\UserMethod::run_method("GenerateShortLoginToken", [ "user_value" => $user->getValue("email") ]);

        $login_type = "login";
        if ($is_admin_login) {
            $login_type = "admin_user_login";
            $expiration_time = strtotime("+1 day");
        }
        else $expiration_time = \API\UserMethod::run_method("GetLoginCookieValidLength", [ "permanent" => $permanent ]);

        $expiration = date("Y-m-d H:i:s", $expiration_time);
        if ($permanent) $expiration = date("Y-m-d H:i:s", $expiration_time);

        $hw_info_id = self::createHwInfo();

        // zruseni platnosti predchozich tokenu na stejne hardware
        $connection->query("UPDATE user_tokens SET", [
            "is_valid" => 0
            ], "WHERE usr_grp_id = ? AND is_valid = 1 AND type='{$login_type}' AND hw_info_id = ?", $id, $hw_info_id);

        $token_table = [
            "token" => $token,
            "type" => $login_type,
            "usr_grp_id" => $id,
            "valid_from" => $connection::literal('NOW()'),
            "created" => $connection::literal('NOW()'),
            "hw_info_id" => $hw_info_id,
            "expiration" => $expiration,
            "is_valid" => 1
            ];



        $connection->query("INSERT INTO user_tokens", $token_table);

        $_SESSION["user_info"] = $user;
        $_SESSION["login_data"] = [
            "token" => $token,
            "is_logged" => true,
            "expiration" => $expiration
            ];


        @setcookie("l_t", $token, $permanent? $expiration_time : 0);
    }

    public static function registerLogin(int $id, bool $permanent = false) {
        self::registerUserLogin($id, $permanent, false);
    }

    public static function loginAdminAs(int $id) {
        self::registerUserLogin($id, false, false);
    }

    public static function logout() {
        global $connection;

        if (empty($_SESSION["login_data"]["is_logged"])) return;

        $token = $_SESSION["login_data"]["token"];
        @setcookie("l_t", "", time() + 100);
        $_SESSION["user_info"] = null;
        $_SESSION["login_data"] = [
            "token" => null,
            "is_logged" => false,
            "expiration" => null
            ];

        $connection->query("UPDATE user_tokens SET", [ "is_valid" => 0], "WHERE token = ?", $token);
    }

    /**
     * Overi prihlaseni uzivatele na zaklade tokenu
     * @param bool $strict Striktni porovnava i hwinfo pripojene k tokenu
     * @return boolean true, pokud je prihlasen
     */
    public static function checkLogin(bool $strict = false): bool {
        global $config;
        global $connection;

        if (empty($_SESSION["login_data"]["is_logged"])) return false;

        $token_info = $connection->fetch("SELECT * FROM user_tokens WHERE token = ? AND is_valid = 1 AND expiration > now() AND (type='login' OR type = 'admin_user_login')", $_SESSION["login_data"]["token"]);

        if (empty($token_info)) return false;


        return true;
    }

    public static function getLoggedUser(): ?Users {
        if (self::checkLogin()) {
            return $_SESSION["user_info"];
        }
        return null;
    }

    public static function createHwInfo() {
        return -1;
        // global $config;
        // global $connection;

        // require_once($config["path"]["absolute"]["root"] . "/external/php/detect_hw/bw_detect.php");
        // require_once($config["path"]["absolute"]["root"] . "/external/php/detect_hw/detect.php");

        // $browser_data = browser_detection('full_assoc');

        // $hw_table = [
        //     "platform" => \API\HardwareDetect::deviceType(),
        //     "ip" => \API\HardwareDetect::ip(),
        //     "hostname" => \API\HardwareDetect::ipHostname(),
        //     "ip_org" => \API\HardwareDetect::ipOrg(),
        //     "country" => \API\HardwareDetect::ipCountry(),
        //     "os" => \API\HardwareDetect::os(),
        //     "from_app" => \API\HardwareDetect::browser(),
        //     "brand" => \API\HardwareDetect::brand(),
        //     "browser_name" => $browser_data["browser_name"],
        //     "browser_working" => $browser_data["browser_working"],
        //     "browser_number" => $browser_data["browser_number"],
        //     "dom" => $browser_data["dom"] ? 1 : 0,
        //     "safe" => $browser_data["safe"] ? 1 : 0,
        //     "os_2" => $browser_data["os"],
        //     "os_number" => $browser_data["os_number"],
        //     "ua_type" => $browser_data["ua_type"],
        //     "browser_math_number" => $browser_data["browser_math_number"] == "" ? null : $browser_data["browser_math_number"],
        //     ];

        // $h = "";
        // foreach ($hw_table as $k=>$v) {
        //     if ($h != "") $h .= " AND ";
        //     $h .= "$k = '$v'";
        // }

        // $exist = $connection->fetch("SELECT * FROM hw_info WHERE $h");

        // if (empty($exist)) {
        //     $hw_table["last_used"] = $connection::literal('NOW()');
        //     $hw_table["created"] = $connection::literal('NOW()');

        //     $connection->query("INSERT INTO hw_info", $hw_table);
        //     return $connection->getInsertId();
        // }
        // else {
        //     $connection->query("UPDATE hw_info SET", ["last_used" => $connection::literal('NOW()')], "WHERE id = ?", $exist["id"]);
        // }

        // return $exist["id"];
    }

    public static function createUser($name, $data, $groups): Users {
        global $connection;
        global $attributes_manager;

        $save_attrs = [];
        $errors = [];
        foreach ($data as $key => $val) {
            $attrType = $attributes_manager->get($key);
            if (!$attrType->canSave($val)) {
                $errors[] = [
                    "type" => $attrType->getLastError(),
                    "attrname" => $key
                ];
            }
            
            $save_attrs[$key] = $attrType->beforeInsertValue($val);
        }

        if (!empty($errors)) {
            throw new \API\Exceptions\ValidationException("", 0, $errors);
        }

        $connection->query("INSERT INTO users_data", array_merge([
            "created" => $connection::literal("now()")
        ], $save_attrs));
        
        return new self($connection->getInsertId());
    }

    function update($attrname, $value) {
        global $connection;
        global $attributes_manager;

        if (!$attributes_manager->get($attrname)->canSave($value)) throw new \API\Exceptions\ValidationException($attributes_manager->get($attrname)->getLastError(), 0);

        $attrname = mb_strtolower($attrname, "utf8");


        $connection->query("UPDATE users_data SET", [
            "edited" => $connection::literal("now()"),
            $attrname => $value
        ], "WHERE id = ?", $this->id);

        //if ($value != $old_value) addToUserHistory($this->getId(), $attr_info["type_id"], $attr_info["value_id"], $value);

        return true;


    }

    public function getValue($attrname) {
        global $attributes_manager;
        if (isset($this->user->$attrname)) {

            $value = $this->user->$attrname;
            $value = $attributes_manager->get($attrname)->beforeReadValue($value);
            return $value;
        }

        return null;
    }
}