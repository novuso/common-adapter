<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Config;

use Novuso\Common\Application\Config\ConfigContainer;
use Novuso\Common\Application\Config\ConfigLoaderInterface;
use Novuso\Common\Application\Config\Exception\ConfigLoaderException;
use Novuso\Common\Application\Filesystem\FilesystemInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * PhpConfigEnvLoader loads merged configuration from PHP files
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class PhpConfigEnvLoader implements ConfigLoaderInterface
{
    /**
     * Filesystem
     *
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * Environment
     *
     * @var string
     */
    protected $environment;

    /**
     * Constructs PhpConfigEnvLoader
     *
     * @param FilesystemInterface $filesystem  The filesystem service
     * @param string              $environment The environment
     */
    public function __construct(FilesystemInterface $filesystem, string $environment)
    {
        $this->filesystem = $filesystem;
        $this->environment = $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, ?string $type = null): ConfigContainer
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
     * {@inheritdoc}
     */
    public function supports($resource, ?string $type = null): bool
    {
        return is_dir($resource) && ($type === null || strtolower($type) === 'php');
    }

    /**
     * Loads configuration from a directory
     *
     * @param string $directory The directory to load
     *
     * @return ConfigContainer
     *
     * @throws ConfigLoaderException
     */
    protected function loadConfig(string $directory): ConfigContainer
    {
        $config = new ConfigContainer();

        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->in($directory)
            ->depth('== 0');

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $name = $this->filesystem->baseName($file->getRealPath(), '.php');
            $data = $this->filesystem->getReturn($file->getRealPath());
            if (!is_array($data)) {
                $message = sprintf(
                    'PHP config must return an array; received (%s) from %s',
                    gettype($data),
                    $file->getRealPath()
                );
                throw new ConfigLoaderException($message);
            }
            $config->set($name, $data);
        }

        return $config;
    }
}