<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony;

use Exception;
use Novuso\Common\Adapter\Service\Symfony\Compiler\CommandFilterCompilerPass;
use Novuso\Common\Adapter\Service\Symfony\Compiler\CommandHandlerCompilerPass;
use Novuso\Common\Adapter\Service\Symfony\Compiler\EventSubscriberCompilerPass;
use Novuso\Common\Adapter\Service\Symfony\Compiler\QueryFilterCompilerPass;
use Novuso\Common\Adapter\Service\Symfony\Compiler\QueryHandlerCompilerPass;
use Novuso\Common\Adapter\Service\Symfony\Compiler\TemplateHelperCompilerPass;
use Novuso\Common\Application\Templating\TemplateHelper;
use Novuso\Common\Domain\Messaging\Command\CommandFilter;
use Novuso\Common\Domain\Messaging\Command\CommandHandler;
use Novuso\Common\Domain\Messaging\Event\EventSubscriber;
use Novuso\Common\Domain\Messaging\Query\QueryFilter;
use Novuso\Common\Domain\Messaging\Query\QueryHandler;
use Novuso\System\Exception\RuntimeException;
use Novuso\System\Utility\Environment;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function Novuso\Common\string;

/**
 * Class Bootstrap
 */
final class Bootstrap
{
    protected ?ContainerInterface $container = null;
    protected bool $booted = false;

    /**
     * Constructs Bootstrap
     */
    public function __construct(
        protected string $environment,
        protected bool $debug,
        protected ?string $envFile,
        protected string $cacheDir,
        protected string $configDir,
        protected string $configFile,
        protected array $paths
    ) {
    }

    /**
     * Retrieves the runtime environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Checks if debug is enabled
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Retrieves the service container
     *
     * @throws Exception When an error occurs
     */
    public function getContainer(): ContainerInterface
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->container;
    }

    /**
     * Boots the system
     *
     * @throws Exception When an error occurs
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->initializeContainer();
        $this->booted = true;
    }

    /**
     * Retrieves the core parameters
     *
     * @throws Exception When error occurs
     */
    protected function getCoreParameters(): array
    {
        $paths = [];
        foreach ($this->paths as $key => $value) {
            $paths[sprintf('path.%s', $key)] = $value;
        }

        return array_merge($this->getEnvParameters(), $paths);
    }

    /**
     * Retrieves the environment parameters
     *
     * Only parameters starting with 'APP__' are retrieved from environment.
     * The parameter name is lower-cased and double underscores are replaced
     * with dots.
     *
     * Example: APP__ENV becomes the parameter app.env
     *
     * @throws Exception When error occurs
     */
    protected function getEnvParameters(): array
    {
        $parameters = [];

        foreach ($_SERVER as $key => $value) {
            if (string($key)->startsWith('APP__')) {
                $parameterKey = string($key)
                    ->replace('__', '.')
                    ->toLowerCase()
                    ->toString();
                $parameters[$parameterKey] = Environment::get($key);
            }
        }

        return $parameters;
    }

    /**
     * Initializes the service container
     *
     * @throws Exception When an error occurs
     */
    protected function initializeContainer(): void
    {
        $class = $this->getContainerClass();
        $cache = new ConfigCache(
            sprintf('%s/%s.php', $this->cacheDir, $class),
            $this->debug
        );
        if (!$cache->isFresh()) {
            $container = $this->buildContainer();
            $container->compile();
            $this->dumpContainer($cache, $container, $class, 'Container');
        }
        require_once $cache->getPath();
        /** @var Container $container */
        $container = new $class();
        $container->set(ParameterBag::class, $container->getParameterBag());
        $this->container = $container;
    }

    /**
     * Retrieves the container class name
     */
    protected function getContainerClass(): string
    {
        return sprintf(
            '%s%sProjectContainer',
            ucfirst($this->environment),
            ($this->debug ? 'Debug' : '')
        );
    }

    /**
     * Builds the service container
     *
     * @throws Exception When an error occurs
     */
    protected function buildContainer(): ContainerBuilder
    {
        if (!is_dir($this->cacheDir)) {
            $success = @mkdir($this->cacheDir, 0777, true);
            if ($success === false && !is_dir($this->cacheDir)) {
                $message = sprintf(
                    'Unable to create the cache directory (%s)',
                    $this->cacheDir
                );
                throw new RuntimeException($message);
            }
        }

        $container = $this->getContainerBuilder();
        $container->addObjectResource($this);
        $this->prepareContainer($container);
        $this->registerContainerConfiguration(
            $this->getContainerLoader($container)
        );
        if ($this->envFile !== null && is_file($this->envFile)) {
            $container->addResource(new FileResource($this->envFile));
        }

        return $container;
    }

    /**
     * Creates a new container builder
     *
     * @return ContainerBuilder
     *
     * @throws Exception When error occurs
     */
    protected function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(
            $this->getCoreParameters()
        ));

        if (
            class_exists('ProxyManager\\Configuration')
            && class_exists('Symfony\\Bridge\\ProxyManager\\LazyProxy\\Instantiator\\RuntimeInstantiator')
        ) {
            $container->setProxyInstantiator(new RuntimeInstantiator());
        }

        return $container;
    }

    /**
     * Prepares the container before it is compiled
     *
     * @param ContainerBuilder $container The container
     *
     * @return void
     */
    protected function prepareContainer(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterListenersPass(
            EventDispatcherInterface::class
        ));
        $container->addCompilerPass(new CommandFilterCompilerPass());
        $container->addCompilerPass(new CommandHandlerCompilerPass());
        $container->addCompilerPass(new EventSubscriberCompilerPass());
        $container->addCompilerPass(new QueryFilterCompilerPass());
        $container->addCompilerPass(new QueryHandlerCompilerPass());
        $container->addCompilerPass(new TemplateHelperCompilerPass());
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');
        $container->registerForAutoconfiguration(CommandFilter::class)
            ->addTag('common.command_filter');
        $container->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('common.command_handler');
        $container->registerForAutoconfiguration(EventSubscriber::class)
            ->addTag('common.event_subscriber');
        $container->registerForAutoconfiguration(QueryFilter::class)
            ->addTag('common.query_filter');
        $container->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('common.query_handler');
        $container->registerForAutoconfiguration(TemplateHelper::class)
            ->addTag('common.template_helper');
    }

    /**
     * Retrieves a loader for the container configuration
     */
    protected function getContainerLoader(
        ContainerBuilder $container
    ): LoaderInterface {
        $locator = new FileLocator($this->configDir);
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new XmlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new ClosureLoader($container)
        ]);

        return new DelegatingLoader($resolver);
    }

    /**
     * Registers the container configuration
     *
     * @throws Exception When a loading error occurs
     */
    protected function registerContainerConfiguration(
        LoaderInterface $loader
    ): void {
        $loader->load($this->configFile);
    }

    /**
     * Dumps the service container to PHP code in the cache
     */
    protected function dumpContainer(
        ConfigCache $cache,
        ContainerBuilder $container,
        string $class,
        string $baseClass
    ): void {
        $dumper = new PhpDumper($container);

        if (
            class_exists('ProxyManager\\Configuration')
            && class_exists('Symfony\\Bridge\\ProxyManager\\LazyProxy\\PhpDumper\\ProxyDumper')
        ) {
            $dumper->setProxyDumper(new ProxyDumper(md5($cache->getPath())));
        }

        $options = [
            'class'      => $class,
            'base_class' => $baseClass,
            'file'       => $cache->getPath()
        ];
        $content = $dumper->dump($options);
        if (!$this->debug) {
            $content = static::stripComments($content);
        }

        $cache->write($content, $container->getResources());
    }

    /**
     * Removes comments from a PHP source string
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     */
    protected static function stripComments(string $source): string
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $rawChunk = '';
        $output = '';
        $tokens = token_get_all($source);
        $ignoreSpace = false;
        for (reset($tokens); false !== $token = current($tokens); next($tokens)) {
            if (is_string($token)) {
                $rawChunk .= $token;
            } elseif (T_START_HEREDOC === $token[0]) {
                $output .= $rawChunk.$token[1];
                do {
                    $token = next($tokens);
                    $output .= $token[1];
                } while ($token[0] !== T_END_HEREDOC);
                $rawChunk = '';
            } elseif (T_WHITESPACE === $token[0]) {
                if ($ignoreSpace) {
                    $ignoreSpace = false;

                    continue;
                }

                // replace multiple new lines with a single newline
                $rawChunk .= preg_replace(['/\n{2,}/S'], "\n", $token[1]);
            } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT])) {
                $ignoreSpace = true;
            } else {
                $rawChunk .= $token[1];

                // The PHP-open tag already has a new-line
                if (T_OPEN_TAG === $token[0]) {
                    $ignoreSpace = true;
                }
            }
        }

        $output .= $rawChunk;

        return $output;
    }
}
