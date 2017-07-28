<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Process;

use Novuso\Common\Application\Process\Exception\ProcessException;
use Novuso\Common\Application\Process\Process;
use Novuso\Common\Application\Process\ProcessErrorBehavior;
use Novuso\Common\Application\Process\ProcessRunnerInterface;
use Novuso\System\Collection\Api\QueueInterface;
use Novuso\System\Collection\LinkedQueue;
use Novuso\System\Exception\DomainException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

/**
 * SymfonyProcessRunner is a Symfony process runner adapter
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class SymfonyProcessRunner implements ProcessRunnerInterface
{
    /**
     * Max concurrent processes
     *
     * @var int
     */
    private $maxConcurrent;

    /**
     * Delay used for usleep
     *
     * @var int
     */
    private $delay;

    /**
     * Process queue
     *
     * @var QueueInterface
     */
    private $queue;

    /**
     * Currently running processes
     *
     * @var array
     */
    private $processes;

    /**
     * Constructs SymfonyProcessRunner
     *
     * @param int $maxConcurrent The max concurrent processes or 0 for no limit
     * @param int $delay         The number of microseconds to delay between process checks
     *
     * @throws DomainException When delay is not a natural number
     */
    public function __construct(int $maxConcurrent = 1, int $delay = 1000)
    {
        if ($delay < 1) {
            $message = sprintf('%s expects delay to be a natural number', __METHOD__);
            throw new DomainException($message);
        }

        $this->maxConcurrent = $maxConcurrent;
        $this->delay = $delay;
        $this->queue = LinkedQueue::of(Process::class);
        $this->processes = [];
    }

    /**
     * Destructs SymfonyProcessRunner
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function attach(Process $process): void
    {
        $this->queue->enqueue($process);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->queue = LinkedQueue::of(Process::class);
        $this->processes = [];
    }

    /**
     * {@inheritdoc}
     */
    public function run(?ProcessErrorBehavior $errorBehavior = null): void
    {
        if ($errorBehavior === null) {
            $errorBehavior = ProcessErrorBehavior::EXCEPTION();
        }

        while (!$this->queue->isEmpty()) {
            $this->init($errorBehavior);
            $this->tick($errorBehavior);
        }

        while (count($this->processes)) {
            $this->tick($errorBehavior);
        }

        $this->clear();
    }

    /**
     * Starts a process if possible
     *
     * @param ProcessErrorBehavior $errorBehavior The process error behavior
     *
     * @return void
     *
     * @throws ProcessException When an error occurs, depending on behavior
     */
    private function init(ProcessErrorBehavior $errorBehavior): void
    {
        if ($this->maxConcurrent !== 0 && count($this->processes) >= $this->maxConcurrent) {
            return;
        }

        try {
            /** @var Process $process */
            $process = $this->queue->dequeue();
            $symfonyProcess = new SymfonyProcess(
                $process->command(),
                $process->directory(),
                $process->environment(),
                $process->input(),
                $process->timeout()
            );

            if ($process->isOutputDisabled()) {
                $symfonyProcess->disableOutput();
            }

            $this->startProcess($symfonyProcess, $process->stdout(), $process->stderr());

            $pid = $symfonyProcess->getPid();
            $this->processes[$pid] = $symfonyProcess;
        } catch (Throwable $e) {
            if ($errorBehavior->value() === ProcessErrorBehavior::EXCEPTION) {
                throw new ProcessException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Performs running checks on processes
     *
     * @param ProcessErrorBehavior $errorBehavior The process error behavior
     *
     * @return void
     *
     * @throws ProcessException When an error occurs, depending on behavior
     */
    private function tick(ProcessErrorBehavior $errorBehavior): void
    {
        usleep($this->delay);

        try {
            /** @var SymfonyProcess $process */
            foreach ($this->processes as $pid => $process) {
                $process->checkTimeout();

                if ($process->isRunning()) {
                    continue;
                }

                // remove from running processes before
                // checking for success
                unset($this->processes[$pid]);

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            }
        } catch (Throwable $e) {
            if ($errorBehavior->value() === ProcessErrorBehavior::EXCEPTION) {
                throw new ProcessException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Starts a process
     *
     * @param SymfonyProcess $process The Symfony process
     * @param callable|null  $stdout  The STDOUT output callback
     * @param callable|null  $stderr  The STDERR output callback
     *
     * @return void
     */
    private function startProcess(SymfonyProcess $process, ?callable $stdout = null, ?callable $stderr = null): void
    {
        $out = SymfonyProcess::OUT;
        $process->start(function ($type, $data) use ($stdout, $stderr, $out) {
            if ($type === $out) {
                if ($stdout !== null) {
                    call_user_func($stdout, $data);
                }
            } else {
                if ($stderr !== null) {
                    call_user_func($stderr, $data);
                }
            }
        });
    }

    /**
     * Stops running processes
     *
     * @return void
     */
    private function stop(): void
    {
        /** @var SymfonyProcess $process */
        foreach ($this->processes as $process) {
            $process->stop(0);
        }

        $this->clear();
    }
}
