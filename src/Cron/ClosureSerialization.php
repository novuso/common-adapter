<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Cron;

use Closure;
use Opis\Closure\SerializableClosure;

/**
 * Trait ClosureSerialization
 */
trait ClosureSerialization
{
    /**
     * Serializes an anonymous function
     */
    protected function serializeClosure(Closure $closure): string
    {
        $wrapper = new SerializableClosure($closure);

        return serialize($wrapper);
    }

    /**
     * Un-serializes an anonymous function
     */
    protected function unserializeClosure(string $serialized): Closure
    {
        /** @var SerializableClosure $wrapper */
        $wrapper = unserialize($serialized);

        return $wrapper->getClosure();
    }
}
