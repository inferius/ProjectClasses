<?php

namespace Tests\Support;

/**
 * Nahrada Nette\Database\Connection pro testy bez databaze: odpovedi podle zacatku SQL, zaznam volani.
 */
final class FakeConnection {
    /** @var array<int, array{sql: string, params: array}> */
    public $calls = [];

    /** @var array<string, callable> zacatek SQL -> fn(array $params) => vysledek */
    private $handlers = [];

    public function on(string $sqlStart, callable $handler): self {
        $this->handlers[$sqlStart] = $handler;
        return $this;
    }

    public function fetch(string $sql, ...$params) {
        return $this->answer($sql, $params);
    }

    public function fetchAll(string $sql, ...$params) {
        return $this->answer($sql, $params) ?? [];
    }

    public function query(string $sql, ...$params) {
        return $this->answer($sql, $params) ?? [];
    }

    private function answer(string $sql, array $params) {
        $this->calls[] = [ "sql" => $sql, "params" => $params ];
        foreach ($this->handlers as $start => $handler) {
            if (strpos($sql, $start) === 0) return $handler($params);
        }
        return null;
    }
}
