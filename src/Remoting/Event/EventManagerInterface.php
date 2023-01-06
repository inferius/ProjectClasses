<?php

namespace API\Event;

/**
 * Interface for EventManager
 * Code from https://github.com/php-fig/fig-standards/blob/master/proposed/event-manager.md
 */
interface EventManagerInterface
{
    /**
     * Attaches a listener to an event
     *
     * @param string $event the event to attach too
     * @param callable $callback a callable function
     * @param int $priority the priority at which the $callback executed
     * @param bool $once if true after first call will be detached
     * @return bool true on success false on failure
     */
    public function attach(string $event, callable $callback, int $priority = 0, bool $once = false);

    /**
     * Detaches a listener from an event
     *
     * @param string $event the event to attach too
     * @param callable $callback a callable function
     * @return bool true on success false on failure
     */
    public function detach(string $event, callable $callback);

    /**
     * Clear all listeners for a given event
     *
     * @param  string $event
     * @return void
     */
    public function clearListeners(string $event);

    /**
     * Trigger an event
     *
     * Can accept an EventInterface or will create one if not passed
     *
     * @param  string|EventInterface $event
     * @param  object|string $target
     * @param  array $argv
     * @return mixed
     */
    public function trigger($event, $target = null, array $argv = []);
}