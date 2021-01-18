<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Config\Directory;

use Novuso\Common\Application\Config\ConfigContainer;
use Novuso\Common\Application\Config\ConfigLoader;
use Novuso\Common\Application\Config\Exception\ConfigLoaderException;
use Novuso\Common\Application\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class YamlConfigEnvLoader
 */
final class YamlConfigEnvLoader implements ConfigLoader
{
    /**
     * Constructs YamlConfigEnvLoader
     */
    public function __construct(
        protected Filesystem $filesystem,
        protected string $environment
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(mixed $resource, ?string $type = null): ConfigContainer
    {
        $config = $this->loadConfig($resource);

        $envDirectory = sprintf('%s/%s', $resource, $this->environment);
        if ($this->filesystem->isDir($envDirectory)) {
            $envConfig = $this->loadConfig($envDirectory);
            $config = $config->merge($envConfig);
        }

        $config->freeze();

        return $config;
    }

    /**
     * @inheritDoc
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->filesystem->isDir($resource)
            && $type !== null
            && in_array(strtolower($type), ['yml', 'yaml']);
    }

    /**
     * Loads configuration from a directory
     *
     * @throws ConfigLoaderException
     */
    protected function loadConfig(string $directory): ConfigContainer
    {
        try {
            $config = new ConfigContainer();

            $files = Finder::create()
                ->files()
                ->name('*.yml')
                ->in($directory)
                ->depth('== 0');

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $name = $file->getBasename('.yml');
                $data = Yaml::parse($this->filesystem->get(sprintf(
                    '%s/%s',
                    $file->getPath(),
                    $file->getFilename()
                )));
                if (!is_array($data)) {
                    $message = sprintf(
                        'YAML config must be an array; received (%s) from %s',
                        gettype($data),
                        sprintf('%s/%s', $file->getPath(), $file->getFilename())
                    );
                    throw new ConfigLoaderException($message);
                }
                $config->set($name, $data);
            }

            return $config;
        } catch (Throwable $e) {
            throw new ConfigLoaderException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
