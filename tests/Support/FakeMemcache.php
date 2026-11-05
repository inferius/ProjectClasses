<?php

namespace Tests\Support;

/**
 * Nahrada Memcache pro testy: hodnoty v poli, zaznam smazanych klicu.
 */
final class FakeMemcache {
    /** @var array<string, mixed> */
    public $data = [];
    /** @var string[] */
    public $deleted = [];

    public function get($key) {
        return $this->data[$key] ?? false;
    }

    public function set($key, $value, $flag = null, $expire = null) {
        $this->data[$key] = $value;
        return true;
    }

    public function delete($key) {
        $this->deleted[] = $key;
        unset($this->data[$key]);
        return true;
    }
}
