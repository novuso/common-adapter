<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\Command\Symfony;

use Novuso\Common\Domain\Messaging\Command\AsynchronousCommandBus;
use Novuso\Common\Domain\Messaging\Command\Command;
use Novuso\Common\Domain\Messaging\Command\CommandMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Class SymfonyMessengerCommandBus
 */
final class SymfonyMessengerCommandBus implements AsynchronousCommandBus
{
    /**
     * Constructs SymfonyMessengerCommandBus
     */
    public function __construct(protected MessageBusInterface $messageBus)
    {
    }

    /**
     * @inheritDoc
     */
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(CommandMessage $message): void
    {
        $this->messageBus->dispatch($message);
    }
}
