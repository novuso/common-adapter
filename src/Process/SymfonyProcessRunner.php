<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Process;

use Novuso\Common\Application\Process\Exception\ProcessException;
use Novuso\Common\Application\Process\Process;
use Novuso\Common\Application\Process\ProcessErrorBehavior;
use Novuso\Common\Application\Process\ProcessRunner;
use Novuso\System\Collection\LinkedQueue;
use Novuso\System\Collection\Type\Queue;
use Novuso\System\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

/**
 * Class SymfonyProcessRunner
 */
final class SymfonyProcessRunner implements ProcessRunner
{
    protected Queue $queue;
    protected array $processes = [];

    /**
     * Constructs SymfonyProcessRunner
     *
     * @param LoggerInterface|null $logger        The logger service
     * @param int                  $maxConcurrent The max concurrent processes
     *                                            or 0 for no limit
     * @param int                  $delay         The number of microseconds to
     *                                            delay between process checks
     * @param int                  $tries         The number of times to try a
     *                                            failed process when error
     *                                            behavior is set to retry
     * @param string               $logLevel      The log level
     *
     * @throws DomainException
     */
    public function __construct(
        protected ?LoggerInterface $logger = null,
        protected int $maxConcurrent = 1,
        protected int $delay = 1000,
        protected int $tries = 3,
        protected string $logLevel = LogLevel::DEBUG
    ) {
        if ($this->delay < 1) {
            $message = sprintf(
                '%s expects delay to be a natural number',
                __METHOD__
            );
            throw new DomainException($message);
        }
        $this->queue = LinkedQueue::of(Process::class);
    }

    /**
     * Destructs SymfonyProcessRunner
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * @inheritDoc
     */
    public function attach(Process $process): void
    {
        $this->queue->enqueue($process);
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->queue = LinkedQueue::of(Process::class);
        $this->processes = [];
    }

    /**
     * @inheritDoc
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
     * @throws ProcessException When an error occurs, depending on behavior
     */
    private function init(ProcessErrorBehavior $errorBehavior): void
    {
        if (
            $this->maxConcurrent !== 0
            && count($this->processes) >= $this->maxConcurrent
        ) {
            return;
        }

        try {
            /** @var Process $process */
            $process = $this->queue->dequeue();
            $symfonyProcess = $this->exchangeProcess($process);

            $this->startProcess(
                $symfonyProcess,
                $process->stdout(),
                $process->stderr()
            );

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

                        if ($iteration > $this->tries) {
                            throw new ProcessFailedException($process);
                        }

                        $iteration++;
                        $symfonyProcess = $this->exchangeProcess($original);

                        $this->startProcess(
                            $symfonyProcess,
                            $original->stdout(),
                            $original->stderr()
                        );

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
     */
    private function startProcess(
        SymfonyProcess $process,
        ?callable $stdout = null,
        ?callable $stderr = null
    ): void {
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
     */
    private function exchangeProcess(Process $process): SymfonyProcess
    {
        $symfonyProcess = SymfonyProcess::fromShellCommandline(
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
     * @codeCoverageIgnore
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
     */
    private function logProcessStarted(SymfonyProcess $process): void
    {
        if ($this->logger === null) {
            return;
        }

        $message = sprintf(
            '[Process]: "%s" started; Working directory: %s',
            $process->getCommandLine(),
            $process->getWorkingDirectory()
        );

        $this->logger->log($this->logLevel, $message);
    }

    /**
     * Logs a process restarted
     */
    private function logProcessRestarted(SymfonyProcess $process): void
    {
        if ($this->logger === null) {
            return;
        }

        $message = sprintf(
            '[Process]: "%s" restarted; Working directory: %s',
            $process->getCommandLine(),
            $process->getWorkingDirectory()
        );

        $this->logger->log($this->logLevel, $message);
    }

    /**
     * Logs a process failed
     */
    private function logProcessFailed(SymfonyProcess $process): void
    {
        if ($this->logger === null) {
            return;
        }

        $message = sprintf(
            '[Process]: "%s" failed; Exit code: %s(%s); Working directory: %s',
            $process->getCommandLine(),
            $process->getExitCode(),
            $process->getExitCodeText(),
            $process->getWorkingDirectory()
        );

        $this->logger->error($message);

        if (!$process->isOutputDisabled()) {
            $message = sprintf(
                '[Process]: Output: {%s}; Error output: {%s}',
                $process->getOutput(),
                $process->getErrorOutput()
            );

            $this->logger->log($this->logLevel, $message);
        }
    }
}
