<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\Command\Symfony;

use Novuso\Common\Domain\Messaging\Command\CommandMessage;
use Novuso\Common\Domain\Messaging\Command\SynchronousCommandBus;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

/**
 * Class SymfonyCommandMessageHandler
 */
final class SymfonyCommandMessageHandler implements MessageHandlerInterface
{
    /**
     * Constructs SymfonyCommandMessageHandler
     */
    public function __construct(protected SynchronousCommandBus $commandBus)
    {
    }

    /**
     * Handles the command message
     *
     * @throws Throwable When an error occurs
     */
    public function __invoke(CommandMessage $commandMessage): void
    {
        $this->commandBus->dispatch($commandMessage);
    }
}
