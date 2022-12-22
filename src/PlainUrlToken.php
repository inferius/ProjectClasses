<?php

namespace API;

final class PlainUrlToken {
    private $token;
    private $plain_token;
    /** @var string[] $groups Skupiny do kterých daný token patří */
    private $groups = [];
    private $slash_at_end;
    private $position;
    private $no_group_position;
    private $custom_data = [];

    public function token($is_plain = false): string {
        return $is_plain ? $this->plain_token : $this->token;
    }

    /**
     * Skupiny tokenu
     * @return string[]
     */
    public function groups(): int { return $this->groups; }

    /**
     * Vrací pozici tokenu
     * @return int
     */
    public function position(): int { return $this->position; }

    /**
     * Vrací informaci o tom, zda je zakončen lomítkem
     * @return bool true pokud je lomítko na konci
     *
     * @deprecated use slashAtEnd instead. Remove on next version
     */
    public function slash_at_end(): bool { return $this->slashAtEnd(); }

    /**
     * Vrací informaci o tom, zda je zakončen lomítkem
     * @return bool true pokud je lomítko na konci
     */
    public function slashAtEnd(): bool { return $this->slash_at_end; }

    /**
     * Projde předané skupiny a zkontroluje, zda daný token ve všech leží. Pokud v nějaké neleží vratí false
     * @param string[] $group seznam skupin
     * @return bool
     */
    public function hasAllGroups(array $group = []): bool {
        if (empty($group)) return true;
        foreach ($group as $grp) {
            if (!in_array(mb_strtolower($grp, "utf8"), $this->groups)) return false;
        }

        return true;
    }

    /**
     * Projde předané skupiny a zkontroluje, zda daný token ve alespoň jedné leží. Pokud v žádné neleží vratí false
     * @param string[] $group seznam skupin
     * @return bool
     */
    public function hasAnyGroups(array $group = []) {
        if (empty($group)) return false;
        foreach ($group as $grp) {
            if (in_array(mb_strtolower($grp, "utf8"), $this->groups)) return true;
        }

        return false;
    }

    /**
     * Vrací true pokud má token nějaké skupiny
     * @return bool
     */
    public function hasGroups(): bool {
        foreach ($this->groups as $grp) return true;

        return false;
    }

    /**
     * Vrací informaci o tom zda se vyskytuje v dané skupině
     * @param string $groupName
     * @return bool
     */
    public function hasGroup(string $groupName): bool {
        return in_array($groupName, $this->groups);
    }

    /**
     * Nastaví vlastní data pro daný token
     * @param string $key
     * @param $value
     * @return void
     */
    public function setCustomDataItem(string $key, $value) {
        $this->custom_data[$key] = $value;
    }

    /**
     * Vrátí vlastní data
     * @param string $key
     * @return mixed|null
     */
    public function getCustomDataItem(string $key) {
        if (empty($this->custom_data[$key])) return null;
        else return $this->custom_data[$key];
    }

    public function __construct(string $token, array $groups, bool $slash_at_end, int $position, int $no_group_position, array $custom_data = []) {
        $this->token = $token;
        $this->plain_token = html_entity_decode(urldecode($token));
        $this->groups = $groups;
        $this->slash_at_end = $slash_at_end;
        $this->position = $position;
        $this->custom_data = $custom_data;
        $this->no_group_position = $no_group_position;
    }

}