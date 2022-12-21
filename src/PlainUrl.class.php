<?php

namespace API;

require_once(__DIR__ . "/Enums/webtype.class.php");

final class PlainUrl {
    private $tokens = [];
    private $original;
    private $contains_empty_token = false;

    public function tokens($only_web = false) { 
        $toks = [];

        $this->each(function ($t) use(&$toks) {
            $toks[] = $t;
        }, $only_web);

        return $toks;
    }

    public function has(string $token, bool $only_web = false): bool {
        return !empty($this->get($token, $only_web));
    }

    public function containsType(int $type): bool {
        if (!\API\Utils\WebTypes::isValidValue($type)) return false;
        $t = false;
        $this->each(function($c) use(&$t, $type) {
            if ($c->type() == $type) {
                $t = true;
                return false;
            }
        });

        return $t;
    }

    public function get(string $token, bool $only_web = false): ?PlainUrlToken {
        foreach ($this->tokens as $t) {
            if ($only_web) {
                if ($t->type() != \API\Utils\WebTypes::Web) continue;
            }

            if ($t->token() == $token) return $t;
        }

        return null;
    }

    public function first(bool $only_web = false): ?PlainUrlToken {
        if (empty($this->tokens)) return null;
        $t = null;
        $this->each(function($tok) use (&$t) {
            $t = $tok;
            return false;
        }, $only_web);
        return $t;
    }

    public function last(bool $only_web = false): ?PlainUrlToken {
        if (empty($this->tokens)) return null;
        $t = null;
        $this->each(function($tok) use (&$t) {
            $t = $tok;
        }, $only_web);
        return $t;
    }


    /**
     * Projde vsechny tokeny
     * $callback = funkce, ktere je predan token, pokud funkce vrati false, je ukonceno prochazeni
     */
    public function each($callback, bool $only_web = false) {
        foreach ($this->tokens as $t) {
            if ($only_web) {
                if ($t->type() != \API\Utils\WebTypes::Web) continue;
            }

            if ($callback($t) === false) break;
        }
    }

    public function hasEmptyToken() {
        return $this->contains_empty_token;
    }

    public function redirectWithoutSlashByToken(string $token) {
        $t = $this->get($token, true);
        $url = self::addSlashEnd($this->original);       

        if (!$t->slash_at_end()) {
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " .  $url);
            exit();
        }
    }

    public function redirectWithoutSlash() {
        $t = $this->last();
        $url = self::addSlashEnd($this->original);

        if ($t != null) {
            if (!$t->slash_at_end()) {
                header("HTTP/1.1 301 Moved Permanently");
                header("Location: " . $url);
                exit();
            }
        }
    }

    public function url(bool $only_web = false): string {
        $url = "/";

        $this->each(function ($t) use (&$url) {
            $url .= $t->token() . "/";
        }, $only_web);

        return $url;
    }

    public function firstUrl(bool $only_web = false): string {
        $url = "/";

        $this->each(function ($t) use (&$url) {
            $url .= $t->token() . "/";
            return false;
        }, $only_web);

        return $url;
    }

    public static function redirectTo(string $url) {
        $url = self::addSlashEnd($url);  
        header("Location: {$url}");
        exit();
    }

    public static function permanentlyRedirectTo(string $url) {
        $url = self::addSlashEnd($url);  
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: {$url}");
        exit();
    }

    public function __construct() {
        $p_url = $_SERVER['REQUEST_URI'];

        $this->original = $p_url;

        $token = "";
        $token_count = 0;
        $web_token_count = 0;

        $iter = function($slash_at_end) use(&$token, &$token_count, &$web_token_count) {
            $type = \API\Utils\WebTypes::Web;
            switch($token) {
                case "blog":
                //case \Localization::getPlainText("url_personal"):
                    if ($web_token_count == 0) $type = \API\Utils\WebTypes::Blog;
                    break;
                default:
                    $web_token_count++;
            }
            $token_count++;

            $this->tokens[]= new PlainUrlToken($token, $type, $slash_at_end);
            
            $token = "";
        };

        $lng = mb_strlen($p_url, "utf-8");
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
}


final class PlainUrlToken {
    private $token;
    private $plain_token;
    private $type;
    private $slash_at_end;

    public function token($is_plain = false): string {
        return $is_plain ? $this->plain_token : $this->token;
    }

    public function type(): int { return $this->type; }
    public function slash_at_end(): bool { return $this->slash_at_end; }

    public function __construct(string $token, int $type, bool $slash_at_end) {
        $this->token = $token;
        $this->plain_token = html_entity_decode(urldecode($token));
        if (\API\Utils\WebTypes::isValidValue($type))
            $this->type = $type;
        $this->slash_at_end = $slash_at_end;
    }

}