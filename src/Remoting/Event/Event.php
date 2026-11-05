<?php

declare(strict_types=1);

namespace API\Event;

class Event implements EventInterface
{
    private string $name;
    private $targer;
    private ?array $params = null;
    private bool $stop_propagation = false;

    public function getName(): string {
        return $this->name;
    }

    public function getTarget() {
        return $this->targer;
    }

    public function getParams(): array {
        return $this->params;
    }

    public function getParam(string $name) {
        return !empty($this->params[$name]) ? $this->params[$name] : false;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setTarget($target = null): void {
        $this->targer = $target;
    }

    public function setParams(array $params): void {
        $this->params = $params;
    }

    public function stopPropagation(bool $flag): void {
        $this->stop_propagation = $flag;
    }

    public function isPropagationStopped(): bool {
        return $this->stop_propagation;
    }
}