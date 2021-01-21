<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command\Queue;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Adapter\Messaging\Event\MessageRetrievalFailed;
use Novuso\Common\Application\Messaging\MessageQueue;
use Novuso\Common\Domain\Messaging\Command\CommandMessage;
use Novuso\Common\Domain\Messaging\Command\SynchronousCommandBus;
use Novuso\Common\Domain\Messaging\Event\EventMessage;
use Novuso\Common\Domain\Messaging\Event\SynchronousEventDispatcher;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\Common\Domain\Messaging\MessageType;
use Novuso\System\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Class WorkerCommand
 *
 * @codeCoverageIgnore
 */
final class WorkerCommand extends Command
{
    public const DEFAULT_SHUTDOWN_EXIT_CODE = 65;

    protected static $defaultName = 'queue:worker';

    protected string $description = 'Consumes messages from a message queue';
    protected bool $shutDown = false;

    /**
     * Constructs WorkerCommand
     *
     * @throws DomainException
     */
    public function __construct(
        protected SynchronousCommandBus $commandBus,
        protected SynchronousEventDispatcher $eventDispatcher,
        protected MessageQueue $messageQueue,
        protected LoggerInterface $logger,
        protected ?string $version = null
    ) {
        parent::__construct();
    }

    /**
     * Handles the signal
     */
    public function handleSignal(): void
    {
        $this->shutDown = true;
        $this->info("\nshutdown signal received");
    }

    /**
     * Fires the command
     *
     * @throws Throwable
     */
    protected function fire(): int
    {
        if (php_sapi_name() === 'cli') {
            // register signal handler
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        $persist = $this->option('persist');
        $queueName = $this->argument('queue');

        if ($this->version !== null) {
            $queueName = sprintf('%s-%s', $queueName, $this->version);
        }

        while (true) {
            try {
                /** @var Message|null $message */
                $message = $this->messageQueue->dequeue(
                    $queueName,
                    $timeout = 10
                );
            } catch (Throwable $e) {
                $this->logger->error(sprintf(
                    'Error retrieving message: %s',
                    $e->getMessage()
                ), ['exception' => $e]);

                $this->eventDispatcher->trigger(new MessageRetrievalFailed(
                    $e->getMessage(),
                    $e->getCode(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTrace(),
                    $e->getTraceAsString()
                ));

                throw $e;
            }

            // check for timeout
            if ($message === null) {
                if ($this->shutDown) {
                    return $this->getExitCode();
                }

                continue;
            }

            try {
                switch ($message->type()->value()) {
                    case MessageType::COMMAND:
                        /** @var CommandMessage $message */
                        $this->commandBus->dispatch($message);
                        break;
                    case MessageType::EVENT:
                        /** @var EventMessage $message */
                        $this->eventDispatcher->dispatch($message);
                        break;
                }

                $this->messageQueue->acknowledge($queueName, $message);

                if (!$persist) {
                    return $this->getExitCode();
                }
            } catch (Throwable $e) {
                $this->messageQueue->reject($queueName, $message);

                throw $e;
            }
        }
    }

    /**
     * Retrieves the exit code to return after success
     */
    protected function getExitCode(): int
    {
        if ($this->shutDown) {
            return (int) $this->option('shutdown-exit');
        }

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

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'persist',
                'p',
                InputOption::VALUE_NONE,
                'Worker should not exit after each message'
            ],
            [
                'shutdown-exit',
                null,
                InputOption::VALUE_REQUIRED,
                'Exit code to signal process should not be restarted',
                static::DEFAULT_SHUTDOWN_EXIT_CODE
            ]
        ];
    }
}
