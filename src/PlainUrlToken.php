<?php

declare(strict_types=1);

namespace API;

final class PlainUrlToken {
    private string $plain_token;

    public function token($is_plain = false): string {
        return $is_plain ? $this->plain_token : $this->token;
    }

    /**
     * Skupiny tokenu
     * @return string[]
     */
    public function groups(): array { return $this->groups; }

    /**
     * Vrací pozici tokenu
     * @return int
     */
    public function position(): int { return $this->position; }

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
    public function hasAnyGroups(array $group = []): bool {
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
    public function setCustomDataItem(string $key, $value): void {
        $this->custom_data[$key] = $value;
    }

    /**
     * Vrátí následující token
     *
     * @param PlainUrlToken $token Token od ktérého se začína
     * @param array|string|false $in_groups
     * @return PlainUrlToken|null
     */
    public function next($in_groups = false): ?PlainUrlToken {
         return $this->plain_url->next($this, $in_groups);
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

    /**
     * @param string[] $groups skupiny, do kterych token patri
     */
    public function __construct(
        private readonly string $token,
        private readonly array $groups,
        private readonly bool $slash_at_end,
        private readonly int $position,
        private readonly int $no_group_position,
        private readonly PlainUrl $plain_url,
        private array $custom_data = []
    ) {
        $this->plain_token = html_entity_decode(urldecode($this->token));
    }

}