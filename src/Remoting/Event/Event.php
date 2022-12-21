<?php

namespace API\Event;

require_once("EventInterfaces.php");

class Event implements EventInterface
{
    private $name;
    private $targer;
    private $params;
    private $stop_propagation = false;

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

    public function setName(string $name) {
        $this->name = $name;
    }

    public function setTarget($target = null) {
        $this->targer = $target;
    }

    public function setParams(array $params) {
        $this->params = $params;
    }

    public function stopPropagation(bool $flag) {
        $this->stop_propagation = $flag;
    }

    public function isPropagationStopped(): bool {
        return $this->stop_propagation;
    }
}