<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Cron;

use Exception;
use Novuso\Common\Adapter\Cron\Exception\LockException;
use Novuso\Common\Application\Mail\Exception\MailException;
use Novuso\Common\Application\Mail\MailService;
use Novuso\Common\Application\Mail\Message\MailMessage;
use Novuso\System\Exception\RuntimeException;
use Novuso\System\Utility\VarPrinter;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class BackgroundJob
 */
final class BackgroundJob
{
    use ClosureSerialization;

    protected array $config;
    protected ?MailService $mailService;
    protected array $lockHandles = [];

    /**
     * Constructs BackgroundJob
     */
    public function __construct(
        protected ContainerInterface $container,
        protected string $name,
        array $config,
        protected string $tempDirectory,
        protected string $fromEmail
    ) {
        $this->config = array_merge(CronManager::getDefaultConfig(), $config);
        if ($this->container->has(MailService::class)) {
            $this->mailService = $this->container->get(MailService::class);
        }
    }

    /**
     * Runs the job
     *
     * @throws Exception When an error occurs
     */
    public function run(): void
    {
        $lockFile = $this->getLockFile();

        try {
            $this->checkMaxRuntime($lockFile);
        } catch (Throwable $e) {
            $this->logError($e);
            $this->notify($e);

            return;
        }

        if (!$this->shouldRun()) {
            return;
        }

        $lockAcquired = false;
        try {
            $this->acquireLock($lockFile);
            $lockAcquired = true;

            if (isset($this->config['closure'])) {
                $this->runClosure();
            } else {
                $this->runCommand();
            }
        } catch (LockException $e) {
            if ($this->container->has(LoggerInterface::class)) {
                /** @var LoggerInterface $logger */
                $logger = $this->container->get(LoggerInterface::class);
                $logger->debug($e->getMessage(), ['job' => $this->name]);
            }
        } catch (Throwable $e) {
            $this->logError($e);
            $this->notify($e);
        }

        if ($lockAcquired) {
            $this->releaseLock($lockFile);
        }
    }

    /**
     * Runs a closure command
     *
     * @throws RuntimeException
     */
    protected function runClosure(): void
    {
        $command = $this->unserializeClosure($this->config['closure']);

        ob_start();

        $returnValue = null;
        try {
            $returnValue = $command($this->container);
        } catch (Throwable $e) {
            $content = ob_get_contents();
            $output = explode("\n", $content);
            ob_end_clean();

            foreach ($output as $line) {
                $this->writeLn($line);
            }

            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $content = ob_get_contents();
        $output = explode("\n", $content);
        ob_end_clean();

        foreach ($output as $line) {
            $this->writeLn($line);
        }

        if ($returnValue !== 0 && $returnValue !== true) {
            $message = sprintf(
                'Command did not return 0 or TRUE; returned (%s) "%s"',
                gettype($returnValue),
                VarPrinter::toString($returnValue)
            );
            throw new RuntimeException($message);
        }
    }

    /**
     * Runs a command-line command
     *
     * @throws RuntimeException When the command does not exit 0
     */
    protected function runCommand(): void
    {
        $command = $this->config['command'];
        exec(sprintf('%s 2>&1', $command), $output, $exitCode);

        foreach ($output as $line) {
            $this->writeLn($line);
        }

        if ($exitCode !== 0) {
            $message = sprintf(
                'Command exited with non-zero status "%s"',
                $exitCode
            );
            throw new RuntimeException($message);
        }
    }

    /**
     * Logs error if possible
     */
    protected function logError(Throwable $exception): void
    {
        if (!$this->container->has(LoggerInterface::class)) {
            return;
        }

        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);
        $logger->error($exception->getMessage(), ['exception' => $exception]);
    }

    /**
     * Sends error notification
     *
     * @throws MailException When an error occurs sending mail
     */
    protected function notify(Throwable $exception): void
    {
        if ($this->mailService === null) {
            return;
        }

        if (empty($this->config['notify'])) {
            return;
        }

        if (is_array($this->config['notify'])) {
            $addresses = $this->config['notify'];
        } else {
            $addresses = explode(',', $this->config['notify']);
        }

        $content = [];
        $content[] = sprintf('Environment: %s', $this->config['environment']);
        $content[] = "\n";
        $content[] = sprintf('Error: %s', $exception->getMessage());
        $content[] = "\n";
        $content[] = sprintf('Code: %s', $exception->getCode());
        $content[] = "\n";
        $content[] = sprintf('File: %s', $exception->getFile());
        $content[] = "\n";
        $content[] = sprintf('Line: %d', $exception->getLine());
        $content[] = "\n";
        $content[] = $exception->getTraceAsString();
        $content[] = "\n";

        $body = implode("\n", $content);

        $message = $this->mailService->createMessage()
            ->setSubject(sprintf('Cron [%s] Needs Some Attention', $this->name))
            ->addFrom($this->fromEmail)
            ->addContent($body, MailMessage::CONTENT_TYPE_PLAIN);

        foreach ($addresses as $address) {
            $message->addTo(trim($address));
        }

        $this->mailService->send($message);
    }

    /**
     * Writes a line to output
     */
    protected function writeLn(string $line): void
    {
        if (!$this->config['output']) {
            return;
        }

        if (
            is_string($this->config['output'])
            && is_writable($this->config['output'])
        ) {
            file_put_contents(
                $this->config['output'],
                sprintf("%s\n", $line),
                FILE_APPEND
            );

            return;
        }

        echo sprintf("%s\n", $line);
    }

    /**
     * Checks if the job should run
     */
    protected function shouldRun(): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves the path to the lock file
     */
    protected function getLockFile(): string
    {
        return sprintf(
            '%s/%s.lock',
            $this->tempDirectory,
            $this->escape($this->name)
        );
    }

    /**
     * Checks the max runtime
     *
     * @throws RuntimeException When the max runtime is exceeded
     */
    protected function checkMaxRuntime(string $lockFile): void
    {
        $maxRuntime = $this->config['max_runtime'];
        if ($maxRuntime === null) {
            return;
        }

        $runtime = $this->getLockLifetime($lockFile);

        if ($runtime > $maxRuntime) {
            $message = sprintf(
                'Max runtime of %d seconds exceeded! Current runtime: %d seconds',
                $maxRuntime,
                $runtime
            );
            throw new RuntimeException($message);
        }
    }

    /**
     * Retrieves the lock file lifetime
     */
    protected function getLockLifetime(string $lockFile): int
    {
        if (!file_exists($lockFile)) {
            return 0;
        }

        // @codeCoverageIgnoreStart
        $pid = file_get_contents($lockFile);
        if (empty($pid)) {
            return 0;
        }

        if (!posix_kill((int) $pid, 0)) {
            return 0;
        }
        // @codeCoverageIgnoreEnd

        $stat = stat($lockFile);

        return (time() - $stat['mtime']);
    }

    /**
     * Acquires a lock for the file
     *
     * @throws RuntimeException When an error occurs
     * @throws LockException When the job is still locked
     */
    protected function acquireLock(string $lockFile): void
    {
        if (array_key_exists($lockFile, $this->lockHandles)) {
            $message = sprintf('Lock already acquired (File: %s)', $lockFile);
            throw new RuntimeException($message);
        }

        // @codeCoverageIgnoreStart
        if (!file_exists($lockFile) && !touch($lockFile)) {
            $message = sprintf(
                'Unable to create lock file (File: %s)',
                $lockFile
            );
            throw new RuntimeException($message);
        }

        $handle = @fopen($lockFile, 'rb+');
        if ($handle === false) {
            $message = sprintf(
                'Unable to open lock file (File: %s)',
                $lockFile
            );
            throw new RuntimeException($message);
        }
        // @codeCoverageIgnoreEnd

        $attempts = 5;
        while ($attempts > 0) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->lockHandles[$lockFile] = $handle;
                ftruncate($handle, 0);
                fwrite($handle, (string) getmypid());

                return;
            }
            usleep(250);
            $attempts--;
        }

        $message = sprintf('Job is still locked (File: %s)', $lockFile);
        throw new LockException($message);
    }

    /**
     * Release the lock for the file
     */
    protected function releaseLock(string $lockFile): void
    {
        if ($this->lockHandles[$lockFile]) {
            ftruncate($this->lockHandles[$lockFile], 0);
            flock($this->lockHandles[$lockFile], LOCK_UN);
        }

        unset($this->lockHandles[$lockFile]);
    }

    /**
     * Sanitizes a string
     */
    protected function escape(string $string): string
    {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9_. -]+/', '', $string);
        $string = trim($string);
        $string = str_replace(' ', '_', $string);
        $string = preg_replace('/_{2,}/', '_', $string);

        return $string;
    }
}
