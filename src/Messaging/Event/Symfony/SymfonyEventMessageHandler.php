<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\Event\Symfony;

use Novuso\Common\Domain\Messaging\Event\EventMessage;
use Novuso\Common\Domain\Messaging\Event\SynchronousEventDispatcher;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

/**
 * Class SymfonyEventMessageHandler
 */
final class SymfonyEventMessageHandler implements MessageHandlerInterface
{
    /**
     * Constructs SymfonyEventMessageHandler
     */
    public function __construct(
        protected SynchronousEventDispatcher $eventDispatcher
    ) {
    }

    /**
     * Handles the event message
     *
     * @throws Throwable When an error occurs
     */
    public function __invoke(EventMessage $eventMessage): void
    {
        $this->eventDispatcher->dispatch($eventMessage);
    }
}
