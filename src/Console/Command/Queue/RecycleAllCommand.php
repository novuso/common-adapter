<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command\Queue;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Application\Messaging\RecyclingMessageQueue;
use Novuso\System\Exception\DomainException;
use Symfony\Component\Console\Input\InputArgument;
use Throwable;

/**
 * Class RecycleAllCommand
 */
final class RecycleAllCommand extends Command
{
    protected static $defaultName = 'queue:recycle-all';

    protected string $description = 'Recycles all failed messages';

    /**
     * Constructs RecycleAllCommand
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

        $this->messageQueue->recycleAll($queue);

        $this->info('Recycled all messages');

        return 0;
    }

    /**
     * @inheritDoc
     */
    protected function getArguments(): array
    {
        return [
            ['queue', InputArgument::REQUIRED, 'The queue name']
        ];
    }
}
