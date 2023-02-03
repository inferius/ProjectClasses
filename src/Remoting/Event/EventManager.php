<?php

namespace API\Event;

final class EventManager implements \API\Event\EventManagerInterface
{
    private $listeners = [];

    public function attach(string $event, callable $callback, int $priority = 0, bool $once = false): bool {
        $this->detach($event, $callback);

        $this->listeners[] = [
            "event" => $event,
            "callback" => $callback,
            "priority" => $priority,
            "once" => $once
        ];

        usort($this->listeners, function ($a, $b){
            return $a["priority"] < $b["priority"];
        });

        return true;
    }

    public function detach(string $event, callable $callback): bool {
        foreach($this->listeners as $key=>$listener) {
            if ($listener["event"] == $event && $listener["callback"] == $callback) {
                unset($this->listeners[$key]);
                return true;
            }
        }

        return false;
    }

    public function clearListeners(string $event) {
        foreach($this->listeners as $key=>$listener) {
            if ($listener["event"] == $event) {
                unset($this->listeners[$key]);
            }
        }
    }

    public function trigger($event, $target = null, array $argv = []) {
        if (is_string($event)) {
            $event_name = $event;
            $event = new Event();
            $event->setName($event_name);
            $event->setTarget($target);
            $event->setParams($argv);
        } elseif ($event instanceof EventInterface) {
            $event_name = $event->getName();
        } else {
            throw new \Exception("EventManager: Param event must be string of instance of EventInterface");
        }

        $result = false;

        foreach($this->listeners as $key=>$listener) {
            if ($listener["event"] == $event_name) {
                $result = $listener["callback"]($event);

                if ($listener["once"]) {
                    $this->detach($event_name, $listener["callback"]);
                }

                if ($event->isPropagationStopped()) {
                    return $result;
                }
            }
        }

        return $result;
    }
}