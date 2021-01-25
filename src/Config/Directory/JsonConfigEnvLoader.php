<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Config\Directory;

use Novuso\Common\Application\Config\ConfigContainer;
use Novuso\Common\Application\Config\ConfigLoader;
use Novuso\Common\Application\Config\Exception\ConfigLoaderException;
use Novuso\Common\Application\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

use function Novuso\Common\json_string;

/**
 * Class JsonConfigEnvLoader
 */
final class JsonConfigEnvLoader implements ConfigLoader
{
    /**
     * Constructs JsonConfigEnvLoader
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
            && strtolower($type) === 'json';
    }

    /**
     * Loads configuration from a directory
     *
     * @throws ConfigLoaderException When JSON is invalid or file cannot be read
     */
    protected function loadConfig(string $directory): ConfigContainer
    {
        try {
            $config = new ConfigContainer();

            $files = Finder::create()
                ->files()
                ->name('*.json')
                ->in($directory)
                ->depth('== 0');

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $name = $file->getBaseName('.json');
                $json = $this->filesystem->get(sprintf(
                    '%s/%s',
                    $file->getPath(),
                    $file->getFilename()
                ));
                $data = json_string($json)->toData();

                if (!is_array($data)) {
                    $message = sprintf(
                        'JSON config must be an object; received (%s) from %s',
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
