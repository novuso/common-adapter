<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Application\Messaging\MessageQueueInterface;
use Novuso\Common\Domain\Messaging\Command\CommandBusInterface;
use Novuso\Common\Domain\Messaging\Command\CommandMessage;
use Novuso\Common\Domain\Messaging\Event\EventDispatcherInterface;
use Novuso\Common\Domain\Messaging\Event\EventMessage;
use Novuso\Common\Domain\Messaging\MessageType;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * QueueWorkerCommand is command that consumes messages from a queue
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class QueueWorkerCommand extends Command
{
    /**
     * User defined exit code for shutdown
     *
     * @var int
     */
    public const DEFAULT_SHUTDOWN_EXIT_CODE = 65;

    /**
     * Command name
     *
     * @var string
     */
    protected $name = 'queue:worker';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Handles the message queue';

    /**
     * Command bus
     *
     * @var CommandBusInterface
     */
    protected $commandBus;

    /**
     * Event dispatcher
     *
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Message queue
     *
     * @var MessageQueueInterface
     */
    protected $messageQueue;

    /**
     * Path to shut down file
     *
     * @var string
     */
    protected $shutDownFilePath;

    /**
     * Fetch delay
     *
     * @var int
     */
    protected $delay;

    /**
     * Constructs QueueWorkerCommand
     *
     * @param CommandBusInterface      $commandBus       The command bus
     * @param EventDispatcherInterface $eventDispatcher  The event dispatcher
     * @param MessageQueueInterface    $messageQueue     The message queue
     * @param string                   $shutDownFilePath Path to shut down file
     * @param int                      $delay            The microseconds to
     *                                                   delay between fetches
     */
    public function __construct(
        CommandBusInterface $commandBus,
        EventDispatcherInterface $eventDispatcher,
        MessageQueueInterface $messageQueue,
        string $shutDownFilePath,
        int $delay = 100000
    ) {
        $this->commandBus = $commandBus;
        $this->eventDispatcher = $eventDispatcher;
        $this->messageQueue = $messageQueue;
        $this->shutDownFilePath = $shutDownFilePath;
        $this->delay = $delay;
        parent::__construct();
    }

    /**
     * Fires the command
     *
     * @return int
     */
    protected function fire(): int
    {
        $persist = $this->option('persist');
        $topic = $this->argument('topic');

        while (true) {
            $message = $this->messageQueue->dequeue($topic);

            if ($message === null) {
                usleep($this->delay);
                continue;
            }

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

            $this->messageQueue->ack($topic, $message);

            if (!$persist) {
                return $this->getExitCode();
            }
        }

        return $this->getExitCode();
    }

    /**
     * Determines the correct exit code
     *
     * @return int
     */
    protected function getExitCode(): int
    {
        if (file_exists($this->shutDownFilePath)) {
            return (int) $this->option('shutdown-exit');
        }

        return 0;
    }

    /**
     * Retrieves the command arguments
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [
            ['topic', InputArgument::REQUIRED, 'The message queue topic name']
        ];
    }

    /**
     * Retrieves the command options
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['persist', 'p', InputOption::VALUE_NONE, 'Worker should not exit after each message'],
            [
                'shutdown-exit',
                null,
                InputOption::VALUE_REQUIRED,
                'Exit code to signal process should not restart',
                self::DEFAULT_SHUTDOWN_EXIT_CODE
            ]
        ];
    }
}
