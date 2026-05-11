<?php

namespace Framework\Events;

/**
 * Event Dispatcher
 * Decouple logic with events/listeners pattern.
 *
 * Usage:
 * Event::listen('user.created', fn($user) => sendWelcomeEmail($user));
 * Event::dispatch('user.created', $user);
 *
 * Event::listen(UserRegistered::class, SendWelcomeEmail::class);
 * Event::dispatch(new UserRegistered($user));
 */
class Event
{
    protected static array $listeners = [];
    protected static array $wildcards = [];
    protected static array $queue = [];
    protected static bool $queued = false;
    protected static array $dispatched = [];

    public static function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        if (str_contains($event, '*')) {
            self::$wildcards[$event][] = ['listener' => $listener, 'priority' => $priority];
            return;
        }

        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        self::$listeners[$event][] = ['listener' => $listener, 'priority' => $priority];
        usort(self::$listeners[$event], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    public static function dispatch(string|object $event, mixed $payload = null): array
    {
        if (is_object($event)) {
            $eventName = get_class($event);
            $payload = $event;
        } else {
            $eventName = $event;
        }

        self::$dispatched[] = $eventName;
        $responses = [];

        $listeners = self::getListeners($eventName);

        foreach ($listeners as $handler) {
            $listener = $handler['listener'];
            $response = self::callListener($listener, $payload, $eventName);

            if ($response === false) {
                break;
            }
            $responses[] = $response;
        }

        return $responses;
    }

    public static function dispatchIf(bool $condition, string|object $event, mixed $payload = null): array
    {
        if ($condition) {
            return self::dispatch($event, $payload);
        }
        return [];
    }

    public static function dispatchUnless(bool $condition, string|object $event, mixed $payload = null): array
    {
        return self::dispatchIf(!$condition, $event, $payload);
    }

    public static function queue(string|object $event, mixed $payload = null): void
    {
        self::$queue[] = ['event' => $event, 'payload' => $payload];
    }

    public static function flush(): array
    {
        $results = [];
        foreach (self::$queue as $item) {
            $results[] = self::dispatch($item['event'], $item['payload']);
        }
        self::$queue = [];
        return $results;
    }

    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
        foreach (self::$wildcards as $pattern => $handlers) {
            unset(self::$wildcards[$pattern]);
        }
    }

    public static function hasListeners(string $event): bool
    {
        return !empty(self::$listeners[$event]) || !empty(self::getWildcardListeners($event));
    }

    public static function dispatched(string $event): bool
    {
        return in_array($event, self::$dispatched);
    }

    public static function reset(): void
    {
        self::$listeners = [];
        self::$wildcards = [];
        self::$queue = [];
        self::$dispatched = [];
    }

    public static function subscribe(string $subscriber): void
    {
        $instance = is_string($subscriber) ? new $subscriber() : $subscriber;
        $instance->subscribe(new self());
    }

    protected static function getListeners(string $event): array
    {
        $listeners = self::$listeners[$event] ?? [];
        $wildcards = self::getWildcardListeners($event);
        return array_merge($listeners, $wildcards);
    }

    protected static function getWildcardListeners(string $event): array
    {
        $matching = [];
        foreach (self::$wildcards as $pattern => $handlers) {
            if (self::wildcardMatch($pattern, $event)) {
                $matching = array_merge($matching, $handlers);
            }
        }
        return $matching;
    }

    protected static function wildcardMatch(string $pattern, string $event): bool
    {
        $regex = '/^' . str_replace(['\\*', '\\'], ['.*', '\\\\'], preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $event);
    }

    protected static function callListener(callable|string $listener, mixed $payload, string $eventName): mixed
    {
        if (is_string($listener)) {
            if (str_contains($listener, '@')) {
                [$class, $method] = explode('@', $listener, 2);
                $instance = new $class();
                return $instance->$method($payload, $eventName);
            }
            $instance = new $listener();
            return $instance->handle($payload, $eventName);
        }

        return $listener($payload, $eventName);
    }
}
