<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Process;

use Novuso\Common\Application\Process\Exception\ProcessException;
use Novuso\Common\Application\Process\Process;
use Novuso\Common\Application\Process\ProcessErrorBehavior;
use Novuso\Common\Application\Process\ProcessRunnerInterface;
use Novuso\System\Collection\Api\QueueInterface;
use Novuso\System\Collection\LinkedQueue;
use Novuso\System\Exception\DomainException;
use Psr\Log\LoggerInterface;
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
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

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
     * @param LoggerInterface $logger        The logger service
     * @param int             $maxConcurrent The max concurrent processes or 0
     *                                       for no limit
     * @param int             $delay         The number of microseconds to
     *                                       delay between process checks
     *
     * @throws DomainException When delay is not a natural number
     */
    public function __construct(LoggerInterface $logger, int $maxConcurrent = 1, int $delay = 1000)
    {
        if ($delay < 1) {
            $message = sprintf('%s expects delay to be a natural number', __METHOD__);
            throw new DomainException($message);
        }

        $this->logger = $logger;
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
            $symfonyProcess = $this->exchangeProcess($process);

            $this->startProcess($symfonyProcess, $process->stdout(), $process->stderr());

            $pid = $symfonyProcess->getPid();
            $this->processes[$pid] = [
                'iteration' => 1,
                'original'  => $process,
                'process'   => $symfonyProcess
            ];

            $this->logProcessStarted($symfonyProcess);
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
            foreach ($this->processes as $pid => $processData) {
                /** @var SymfonyProcess $process */
                $process = $processData['process'];
                $process->checkTimeout();

                if ($process->isRunning()) {
                    continue;
                }

                // remove current process before
                // checking for success
                unset($this->processes[$pid]);

                if (!$process->isSuccessful()) {
                    $this->logProcessFailed($process);
                    if ($errorBehavior->value() === ProcessErrorBehavior::RETRY) {
                        /** @var Process $original */
                        $original = $processData['original'];
                        $iteration = $processData['iteration'];

                        if ($iteration > 3) {
                            throw new ProcessFailedException($process);
                        }

                        $iteration++;
                        $symfonyProcess = $this->exchangeProcess($original);

                        $this->startProcess($symfonyProcess, $original->stdout(), $original->stderr());

                        $pid = $symfonyProcess->getPid();
                        $this->processes[$pid] = [
                            'iteration' => $iteration,
                            'original'  => $original,
                            'process'   => $symfonyProcess
                        ];

                        $this->logProcessRestarted($symfonyProcess);
                    }
                    if ($errorBehavior->value() === ProcessErrorBehavior::EXCEPTION) {
                        throw new ProcessFailedException($process);
                    }
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
     * Creates a Symfony process instance
     *
     * @param Process $process The process
     *
     * @return SymfonyProcess
     */
    private function exchangeProcess(Process $process): SymfonyProcess
    {
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

        return $symfonyProcess;
    }

    /**
     * Stops running processes
     *
     * @return void
     */
    private function stop(): void
    {
        foreach ($this->processes as $processData) {
            /** @var SymfonyProcess $process */
            $process = $processData['process'];
            $process->stop(0);
        }

        $this->clear();
    }

    /**
     * Logs a process started
     *
     * @param SymfonyProcess $process The Symfony process
     *
     * @return void
     */
    private function logProcessStarted(SymfonyProcess $process): void
    {
        $message = sprintf(
            'Process "%s" started; Working directory: %s',
            $process->getCommandLine(),
            $process->getWorkingDirectory()
        );
        $this->logger->info($message);
    }

    /**
     * Logs a process restarted
     *
     * @param SymfonyProcess $process The Symfony process
     *
     * @return void
     */
    private function logProcessRestarted(SymfonyProcess $process): void
    {
        $message = sprintf(
            'Process "%s" restarted; Working directory: %s',
            $process->getCommandLine(),
            $process->getWorkingDirectory()
        );
        $this->logger->warning($message);
    }

    /**
     * Logs a process failed
     *
     * @param SymfonyProcess $process The Symfony process
     *
     * @return void
     */
    private function logProcessFailed(SymfonyProcess $process): void
    {
        $message = sprintf(
            'Process "%s" failed; Exit code: %s(%s); Working directory: %s',
            $process->getCommandLine(),
            $process->getExitCode(),
            $process->getExitCodeText(),
            $process->getWorkingDirectory()
        );
        $this->logger->error($message);
        if (!$process->isOutputDisabled()) {
            $message = sprintf(
                'Output: {%s}; Error output: {%s}',
                $process->getOutput(),
                $process->getErrorOutput()
            );
            $this->logger->error($message);
        }
    }
}
