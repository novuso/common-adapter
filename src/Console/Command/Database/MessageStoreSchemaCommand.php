<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command\Database;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Adapter\Messaging\MessageStore\DbalMessageStore;
use Novuso\System\Exception\DomainException;
use Throwable;

/**
 * Class MessageStoreSchemaCommand
 */
final class MessageStoreSchemaCommand extends Command
{
    protected static $defaultName = 'database:message-store-schema';

    protected string $description = 'Creates schema for a DBAL message store';

    /**
     * Constructs MessageStoreSchemaCommand
     *
     * @throws DomainException
     */
    public function __construct(protected DbalMessageStore $messageStore)
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
        $this->messageStore->createSchema();

        $this->info('Message store schema created');

        return 0;
    }
}
