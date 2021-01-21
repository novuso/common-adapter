<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command\Queue;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Application\Messaging\RecyclingMessageQueue;
use Novuso\Common\Domain\Messaging\MessageId;
use Novuso\System\Exception\DomainException;
use Symfony\Component\Console\Input\InputArgument;
use Throwable;

/**
 * Class RecycleCommand
 *
 * @codeCoverageIgnore
 */
final class RecycleCommand extends Command
{
    protected static $defaultName = 'queue:recycle';

    protected string $description = 'Recycles a failed message by ID';

    /**
     * Constructs RecycleCommand
     *
     * @throws DomainException
     */
    public function __construct(protected RecyclingMessageQueue $messageQueue)
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
        /** @var string $queue */
        $queue = $this->argument('queue');
        /** @var string $messageId */
        $messageId = $this->argument('message_id');

        $this->messageQueue->recycle($queue, MessageId::fromString($messageId));

        $this->info(sprintf('Recycled message: %s', $messageId));

        return 0;
    }

    /**
     * @inheritDoc
     */
    protected function getArguments(): array
    {
        return [
            ['queue', InputArgument::REQUIRED, 'The queue name'],
            ['message_id', InputArgument::REQUIRED, 'The message ID']
        ];
    }
}
