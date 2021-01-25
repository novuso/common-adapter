<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Cron;

use Closure;
use Novuso\Common\Application\Mail\MailService;
use Novuso\Common\Application\Process\Process;
use Novuso\Common\Application\Process\ProcessBuilder;
use Novuso\Common\Application\Process\ProcessErrorBehavior;
use Novuso\Common\Application\Process\ProcessRunner;
use Novuso\Common\Domain\Value\DateTime\Timezone;
use Novuso\System\Collection\HashTable;
use Novuso\System\Collection\Type\Table;
use Novuso\System\Exception\DomainException;
use Novuso\System\Exception\RuntimeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Class CronManager
 */
final class CronManager
{
    use ClosureSerialization;

    protected static array $defaultConfig = [
        'environment' => 'default',
        'enabled'     => true,
        'passthru'    => true,
        'output'      => true,
        'notify'      => null,
        'max_runtime' => null
    ];

    protected ContainerInterface $container;
    protected Timezone $timezone;
    protected Table $jobs;
    protected string $phpExec;
    protected Scheduler $scheduler;
    protected bool $notificationAvailable;

    /**
     * Constructs CronManager
     *
     * @param ProcessRunner $processRunner The process runner
     * @param string        $rootDirectory The project root directory
     * @param string        $containerPath Path to a PHP file that returns a
     *                                     service container
     * @param string        $scriptPath    Path to the run_cron script
     * @param string        $timezone      Application timezone
     * @param array         $config        Global configuration overrides
     * @param string|null   $fromEmail     The FROM email address for
     *                                     notifications
     *
     * @throws DomainException
     * @throws RuntimeException
     */
    public function __construct(
        protected ProcessRunner $processRunner,
        protected string $rootDirectory,
        protected string $containerPath,
        protected string $scriptPath,
        string $timezone,
        protected array $config = [],
        protected ?string $fromEmail = null
    ) {
        if (!is_dir($this->rootDirectory)) {
            $message = sprintf(
                'Root directory %s does not exist',
                $this->rootDirectory
            );
            throw new DomainException($message);
        }

        if (!file_exists($this->containerPath)) {
            $message = sprintf(
                'Container path %s does not exist',
                $this->containerPath
            );
            throw new DomainException($message);
        }

        $container = require $containerPath;
        if (!($container instanceof ContainerInterface)) {
            $message = sprintf(
                'Container path must return an instance of %s: %s',
                ContainerInterface::class,
                $containerPath
            );
            throw new DomainException($message);
        }

        $this->container = $container;

        if (!file_exists($this->scriptPath)) {
            $message = sprintf(
                'Script path %s does not exist',
                $this->scriptPath
            );
            throw new DomainException($message);
        }

        $this->timezone = Timezone::fromString($timezone);
        $this->config = array_merge(static::getDefaultConfig(), $config);
        $this->jobs = HashTable::of('string', 'array');

        $phpExec = (new PhpExecutableFinder())->find();

        // @codeCoverageIgnoreStart
        if ($phpExec === false) {
            throw new RuntimeException('Cannot locate PHP binary');
        }
        // @codeCoverageIgnoreEnd

        $this->phpExec = $phpExec;
        $this->scheduler = new Scheduler($this->timezone);

        // check for notification support
        if ($container->has(MailService::class) && $fromEmail !== null) {
            $this->notificationAvailable = true;
        } else {
            $this->notificationAvailable = false;
        }
    }

    /**
     * Retrieves the default configuration
     */
    public static function getDefaultConfig(): array
    {
        return static::$defaultConfig;
    }

    /**
     * Adds a job
     *
     * @throws DomainException When the configuration is invalid
     */
    public function add(string $name, array $config): void
    {
        // job name must be unique
        if ($this->jobs->has($name)) {
            $message = sprintf('Duplicate job %s', $name);
            throw new DomainException($message);
        }

        // check for required configuration
        if (!isset($config['schedule'])) {
            $message = sprintf('"schedule" is required for %s job', $name);
            throw new DomainException($message);
        }
        if (!isset($config['command'])) {
            $message = sprintf('"command" is required for %s job', $name);
            throw new DomainException($message);
        }

        // check if notifications are available
        if (isset($config['notify']) && !$this->notificationAvailable) {
            $message = sprintf(
                'Container must have %s registered and CronManager must have FROM email to handle notifications',
                MailService::class
            );
            throw new DomainException($message);
        }

        // save anonymous functions to a different key
        if ($config['command'] instanceof Closure) {
            $config['closure'] = $config['command'];
            unset($config['command']);
        }

        $config = array_merge($this->config, $config);
        $this->jobs->set($name, $config);
    }

    /**
     * Runs scheduled jobs
     *
     * @throws Throwable
     */
    public function run(): void
    {
        foreach ($this->jobs as $name => $config) {
            if (!$this->scheduler->isDue($config['schedule'])) {
                continue;
            }
            $process = $this->buildProcess($name, $config);
            $this->processRunner->attach($process);
        }

        $this->processRunner->run(ProcessErrorBehavior::IGNORE());
    }

    /**
     * Builds the command process
     *
     * @param string $name   The job name
     * @param array  $config The job config
     *
     * @return Process
     *
     * @throws Throwable
     */
    protected function buildProcess(string $name, array $config): Process
    {
        if (isset($config['closure'])) {
            $config['closure'] = $this->serializeClosure($config['closure']);
        }

        $processBuilder = new ProcessBuilder();

        if ($config['passthru']) {
            $processBuilder->stdout(
                function ($output) {
                    if (trim($output) === '') {
                        return;
                    }
                    echo rtrim($output, "\n")."\n";
                }
            );
            $processBuilder->stderr(
                function ($output) {
                    if (trim($output) === '') {
                        return;
                    }
                    echo rtrim($output, "\n")."\n";
                }
            );
        }

        $environment = getenv();
        foreach ($environment as $key => $value) {
            $processBuilder->setEnv($key, $value);
        }

        return $processBuilder
            ->directory($this->rootDirectory)
            ->arg($this->phpExec)
            ->arg($this->scriptPath)
            ->arg($this->containerPath)
            ->arg($name)
            ->arg(http_build_query($config))
            ->arg($this->fromEmail ?: 'NONE')
            ->getProcess();
    }
}
