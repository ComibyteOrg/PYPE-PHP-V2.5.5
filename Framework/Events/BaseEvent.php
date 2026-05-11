<?php

namespace Framework\Events;

abstract class BaseEvent
{
    public function dispatch(): array
    {
        return Event::dispatch($this);
    }

    public function queue(): void
    {
        Event::queue($this);
    }
}
