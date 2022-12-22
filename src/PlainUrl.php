<?php

namespace API;


final class PlainUrl {
    /**
     * @var PlainUrlToken[]
     */
    private $tokens = [];
    private $original;
    private $contains_empty_token = false;

    private static $groups = [];


    /**
     * Vrátí seznam tokenu
     * @param array|string|false $in_groups Obsahuje seznam skupin nebo false, pokud obsahuje false vrátí jen pokud není v žádné skupině
     * @return PlainUrlToken[]
     */
    public function tokens($in_groups = false): array {
        $toks = [];

        $this->each(function ($t) use(&$toks) {
            $toks[] = $t;
        }, $in_groups);

        return $toks;
    }

    /**
     * Vrací informaci o tom zda obsahuje daný token
     * @param string $token
     * @param array|string|false $in_groups
     * @return bool
     */
    public function has(string $token, $in_groups = false): bool {
        return !empty($this->get($token, $in_groups));
    }

    /**
     * Vrátí informaci o tom, zda obahuje daný typ tokenu
     *
     * @param array|string $in_groups
     * @return bool
     */
    public function containsGroups($in_groups): bool {
        if (empty($in_groups)) return false;

        if (is_string($in_groups)) $in_groups = [ $in_groups ];

        $groups = PlainUrl::groupParser($in_groups);


        foreach ($this->tokens as $token) {
            if ($token->hasAnyGroups($groups["not_in"])) return false;
            if (empty($groups["in"])) continue;
            else {
                $grp_i = [];
                foreach ($groups["in"] as $group_in) {
                    if ($token->hasGroup($group_in)) {
                        continue;
                    }
                    $grp_i[] = $group_in;
                }
                $groups["in"] = $grp_i;
            }
        }

        if (!empty($groups["in"])) return false;

        return true;
    }

    /**
     * Vratí token
     * @param string $token
     * @param array|string|false $in_groups
     * @return PlainUrlToken|null
     */
    public function get(string $token, $in_groups = false) {
        return $this->each(function($t) use ($token) { if ($t->token() == $token) return false; }, $in_groups);
    }

    /**
     * Vrátí první token
     *
     * @param array|string|false $in_groups
     * @return PlainUrlToken|null
     */
    public function first($in_groups = false) {
        if (empty($this->tokens)) return null;
        return $this->each(function() { return false; }, $in_groups);
    }

    /**
     * Vrátí poslední token
     *
     * @param array|string|false $in_groups
     * @return PlainUrlToken|null
     */
    public function last($in_groups = false) {
        if (empty($this->tokens)) return null;
        return $this->each(function($tok) {}, $in_groups);
    }

    /**
     * Projde všechny tokeny
     * @param callable $callback funkce, ktere je predan token, pokud funkce vrati false, je ukonceno prochazeni
     * @param array|string|false $in_groups
     * @return PlainUrlToken|null
     */
    public function each(callable $callback, $in_groups = false): PlainUrlToken {
        $last = null;
        foreach ($this->tokens as $t) {
            if (empty($in_groups)) {
                //if ($t->hasGroups()) continue;
            }
            else {
                if (is_string($in_groups)) {
                    $not_in = PlainUrl::updateGroupName($in_groups);
                    if ($not_in && $t->hasGroup($in_groups)) continue;
                    else if (!$not_in && !$t->hasGroup($in_groups)) continue;

                }
                else if (is_array($in_groups)) {
                    $group_data = PlainUrl::groupParser($in_groups);
                    if ($t->hasAnyGroups($group_data["not_in"])) continue;
                    else if (!$t->hasAllGroups($group_data["in"])) continue;
                }
            }

            if ($callback($t) === false) return $t;
            $last = $t;
        }

        return $last;
    }

    /**
     * Vrátí informaci o tom, zda obsahuje prázdné tokeny
     * @return bool
     */
    public function hasEmptyToken() {
        return $this->contains_empty_token;
    }

    public function redirectWithoutSlashByToken(string $token) {
        $t = $this->get($token, true);
        $url = self::addSlashEnd($this->original);       

        if (!$t->slashAtEnd()) {
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " .  $url);
            exit();
        }
    }

    /**
     * Zparsuje skupiny
     * @param string[] $group_list
     * @return array|array[]
     */
    private static function groupParser(array $group_list = []): array {
        $r = [
            "in" => [],
            "not_in" => []
        ];

        $used_grps = [];

        foreach ($group_list as $grp) {
            $not_in = PlainUrl::updateGroupName($grp);
            if (in_array($grp, $used_grps)) {
                trigger_error("Group was used before yet. Current group will be skipped.", E_USER_NOTICE);
                continue;
            }
            $used_grps[] = $grp;

            if ($not_in) $r["not_in"][] = $grp;
            else $r["in"][] = $grp;
        }

        return $r;
    }

    /**
     * Upraví název skupiny pres referenci a pokud na začátku obsahuje negaci, vrátí true jinak false
     * @param string $grp
     * @return bool
     */
    private static function updateGroupName(string &$grp): bool {
        if (\Nette\Utils\Strings::startsWith($grp, "!")) {
            $grp = \Nette\Utils\Strings::substring($grp, 1);
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Provede přesměrování na stejnou adresu, ale s lomítkem na konci
     * @return void
     */
    public function redirectWithoutSlash() {
        $t = $this->last();
        $url = self::addSlashEnd($this->original);

        if ($t != null) {
            if (!$t->slashAtEnd()) {
                header("HTTP/1.1 301 Moved Permanently");
                header("Location: " . $url);
                exit();
            }
        }
    }

    /**
     * Vrátí URL adresu složenou s tokenu
     * @param array|string|false $in_groups
     * @return string
     */
    public function url($in_groups = false): string {
        $url = "/";

        $this->each(function ($t) use (&$url) {
            $url .= $t->token() . "/";
        }, $in_groups);

        return $url;
    }

    /**
     * Vrátí první část URL
     * @param array|string|false $in_groups
     * @return string
     */
    public function firstUrl($in_groups = false): string {
        $url = "/";

        $this->each(function ($t) use (&$url) {
            $url .= $t->token() . "/";
            return false;
        }, $in_groups);

        return $url;
    }

    /**
     * Provede přesměrování na danou url
     * @param string $url
     * @return void
     */
    public static function redirectTo(string $url) {
        $url = self::addSlashEnd($url);  
        header("Location: {$url}");
        exit();
    }

    /**
     * Provede přesměrování na předanou adresu s hlavičkou 301 Moved Permanently
     * @param string $url
     * @return void
     */
    public static function permanentlyRedirectTo(string $url) {
        $url = self::addSlashEnd($url);  
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: {$url}");
        exit();
    }

    /**
     * Parsovani URL adresy
     * @param string|null $custom_url Předa URL adresu, pokud neni předána použije se z $_SERVER['REQUEST_URI']
     * @param bool $single_group Pokud je true, znamena, ze každy token může být jen členem jedne skupiny
     */
    public function __construct($custom_url = null, bool $single_group = true) {
        if (empty($custom_url)) $p_url = $_SERVER['REQUEST_URI'];
        $p_url = $custom_url;

        $this->original = $p_url;

        $token = "";
        $token_count = 0;
        $no_group_token = 0;

        $iter = function($slash_at_end) use(&$token, &$token_count, &$no_group_token, $single_group) {
            $grps = [];
            $custom_data = [];

            foreach (PlainUrl::$groups as $key => $group) {
                $custom_data = [];
                if (is_callable($group["checker"])) {
                    $state = $group["checker"]($token, $token_count, $no_group_token, $custom_data);
                    if (!empty($state)) {
                        $grps[] = $key;
                        if ($single_group) break;
                    }
                }
            }

            $this->tokens[] = new PlainUrlToken($token, $grps, $slash_at_end, $token_count++, $no_group_token, $custom_data);

            if (count($grps) == 0) $no_group_token++;

            
            $token = "";
        };

        $lng = mb_strlen($p_url, "utf8");
        $last_backslash = false;
        for ($i = 0; $i < $lng; $i++) {
            $ch = $p_url[$i];
            if ($ch == "/") {
                if ($last_backslash) {
                    $this->contains_empty_token = true;
                }
                $last_backslash = true;
                if ($token == "") continue;
                
                $iter(true);
                continue;
            }
            else {
                $last_backslash = false;
            }
            $token .= $ch;
        }

        if ($token != "") $iter(false);

    }

    private static function addSlashEnd($url) {
        $endsWith = function($haystack, $needle) {
            $length = strlen($needle);
            if ($length == 0) {
                return true;
            }

            return (substr($haystack, -$length) === $needle);
        };

        if ($endsWith($url, "/")) return $url;
        else return $url . "/";
    }

    private static function isTokenType(string $name, $token) {
        $name = mb_strtolower($name, "utf8");

    }

    private static function isValidGroupName($groupName): bool {
        if ($groupName === false || is_string($groupName)) return true;
        return false;
    }

    /**
     * Zaregistruje novou skupinu stránek
     * @param string $name Název skupiny (nesmí obsahovat ! na začátku)
     * @param callable $fnc_checker Funkce, která zkontroluje zda patří do dané skupiny, pokud do ní patří musí vracet true
     *  - Format funkce function (string $token, int $position|pozice v tokenech, int $no_grp_position|pozice v tokenech, ktere nemaji skupinu, array &custom_data|asociativni pole předané tokenu a přistupné)
     * @return void
     */
    public static function registerTokenTypeChecker(string $name, callable $fnc_checker) {
        if (\Nette\Utils\Strings::startsWith($name, "!")) throw new \InvalidArgumentException("Name hasn't starts with !");
        $name = mb_strtolower($name, "utf8");

        if (!empty(PlainUrl::$groups[$name])) {
            trigger_error("Web type with this name has exist. Old web type will be overwritten.", E_USER_NOTICE);
        }

        PlainUrl::$groups[$name] = [
            "checker" => $fnc_checker
        ];
    }
}


