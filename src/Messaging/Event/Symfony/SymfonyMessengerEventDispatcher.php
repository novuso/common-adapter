<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\Event\Symfony;

use Novuso\Common\Domain\Messaging\Event\AsynchronousEventDispatcher;
use Novuso\Common\Domain\Messaging\Event\Event;
use Novuso\Common\Domain\Messaging\Event\EventMessage;
use Novuso\Common\Domain\Messaging\Event\EventSubscriber;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Class SymfonyMessengerEventDispatcher
 */
final class SymfonyMessengerEventDispatcher implements AsynchronousEventDispatcher
{
    /**
     * Constructs SymfonyMessengerEventDispatcher
     */
    public function __construct(protected MessageBusInterface $messageBus)
    {
    }

    /**
     * @inheritDoc
     */
    public function trigger(Event $event): void
    {
        $this->dispatch(EventMessage::create($event));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(EventMessage $message): void
    {
        $this->messageBus->dispatch($message);
    }

    /**
     * @inheritDoc
     */
    public function register(EventSubscriber $subscriber): void
    {
        // no operation
    }

    /**
     * @inheritDoc
     */
    public function unregister(EventSubscriber $subscriber): void
    {
        // no operation
    }

    /**
     * @inheritDoc
     */
    public function addHandler(
        string $eventType,
        callable $handler,
        int $priority = 0
    ): void {
        // no operation
    }

    /**
     * @inheritDoc
     */
    public function getHandlers(?string $eventType = null): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function hasHandlers(?string $eventType = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function removeHandler(string $eventType, callable $handler): void
    {
        // no operation
    }
}
