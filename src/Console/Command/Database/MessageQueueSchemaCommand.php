<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command\Database;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Adapter\Messaging\MessageQueue\MySqlMessageQueue;
use Novuso\System\Exception\DomainException;
use Throwable;

/**
 * Class MessageQueueSchemaCommand
 *
 * @codeCoverageIgnore
 */
final class MessageQueueSchemaCommand extends Command
{
    protected static $defaultName = 'database:message-queue-schema';

    protected string $description = 'Creates schema for a MySQL message queue';

    /**
     * Constructs MessageQueueSchemaCommand
     *
     * @throws DomainException
     */
    public function __construct(protected MySqlMessageQueue $messageQueue)
    {
        parent::__construct();
    }

    /**
     * Fires the command
     *
     * @throws Throwable
     */
    protected function fire(): int
    {
        $this->messageQueue->createSchema();

        $this->info('Message queue schema created');

        return 0;
    }
}
