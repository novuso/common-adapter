<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\FileStorage;

use Google\Cloud\Storage\StorageClient;
use Novuso\Common\Application\FileStorage\Exception\FileStorageException;
use Novuso\Common\Application\FileStorage\FileStorage;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\System\Collection\HashSet;

/**
 * Class GoogleCloudFileStorage
 */
final class GoogleCloudFileStorage implements FileStorage
{
    /**
     * Constructs GoogleCloudFileStorage
     */
    public function __construct(
        protected StorageClient $client,
        protected string $bucket,
        protected string $protocol = 'gs'
    ) {
        $this->client->registerStreamWrapper($protocol);
    }

    /**
     * @inheritDoc
     */
    public function putFile(string $path, mixed $contents): void
    {
        $handle = @fopen($this->getStreamPath($path), 'wb');

        if ($handle === false) {
            $message = sprintf('Unable to open stream for writing: %s', $path);
            throw new FileStorageException($message);
        }

        if (is_string($contents)) {
            $stream = fopen('php://temp', 'rb+');
            if (!empty($contents)) {
                fwrite($stream, $contents);
                fseek($stream, 0);
            }
            $contents = $stream;
        }

        while ($line = fgets($contents, 1024)) {
            fwrite($handle, $line, 1024);
        }

        fclose($contents);
        fclose($handle);
    }

    /**
     * @inheritDoc
     */
    public function getFileContents(string $path): string
    {
        $contents = @file_get_contents($this->getStreamPath($path));

        if ($contents === false) {
            $message = sprintf('Unable to read file contents: %s', $path);
            throw new FileStorageException($message);
        }

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function getFileResource(string $path): mixed
    {
        $handle = @fopen($this->getStreamPath($path), 'rb');

        if ($handle === false) {
            $message = sprintf('Unable to open stream for reading: %s', $path);
            throw new FileStorageException($message);
        }

        return $handle;
    }

    /**
     * @inheritDoc
     */
    public function hasFile(string $path): bool
    {
        return is_file($this->getStreamPath($path));
    }

    /**
     * @inheritDoc
     */
    public function removeFile(string $path): void
    {
        if (!$this->hasFile($path)) {
            return;
        }

        $success = @unlink($this->getStreamPath($path));

        if ($success === false) {
            $message = sprintf('Unable to remove file: %s', $path);
            throw new FileStorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function copyFile(string $source, string $destination): void
    {
        if (!$this->hasFile($source)) {
            $message = sprintf('File not found: %s', $source);
            throw new FileStorageException($message);
        }

        $success = @copy(
            $this->getStreamPath($source),
            $this->getStreamPath($destination)
        );

        if ($success === false) {
            $message = sprintf(
                'Unable to copy file %s to %s',
                $source,
                $destination
            );
            throw new FileStorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function moveFile(string $source, string $destination): void
    {
        if (!$this->hasFile($source)) {
            $message = sprintf('File not found: %s', $source);
            throw new FileStorageException($message);
        }

        $success = @rename(
            $this->getStreamPath($source),
            $this->getStreamPath($destination)
        );

        if ($success === false) {
            $message = sprintf(
                'Unable to move file %s to %s',
                $source,
                $destination
            );
            throw new FileStorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): int
    {
        if (!$this->hasFile($path)) {
            $message = sprintf('File not found: %s', $path);
            throw new FileStorageException($message);
        }

        $size = @filesize($this->getStreamPath($path));

        if ($size === false) {
            $message = sprintf('Unable to read file size: %s', $path);
            throw new FileStorageException($message);
        }

        return $size;
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): DateTime
    {
        if (!$this->hasFile($path)) {
            $message = sprintf('File not found: %s', $path);
            throw new FileStorageException($message);
        }

        $mTime = @filemtime($this->getStreamPath($path));

        if ($mTime === false) {
            $message = sprintf('Unable to read last modified time: %s', $path);
            throw new FileStorageException($message);
        }

        return DateTime::fromTimestamp($mTime);
    }

    /**
     * @inheritDoc
     */
    public function listFiles(?string $path = null): array
    {
        $path = $this->normalizePath($path);

        $resources = $this->listResources($path);

        $files = HashSet::of('string');
        foreach ($resources as $resource) {
            $relativePath = $resource;
            if ($path !== null) {
                $relativePath = ltrim(substr($resource, strlen($path)), '/');
            }
            if (str_contains($relativePath, '/')) {
                continue;
            }
            $file = $path !== null
                ? sprintf('%s/%s', $path, $relativePath)
                : $relativePath;
            $files->add($file);
        }

        return $files->toArray();
    }

    /**
     * @inheritDoc
     */
    public function listFilesRecursively(?string $path = null): array
    {
        $path = $this->normalizePath($path);

        $resources = $this->listResources($path);

        $files = HashSet::of('string');
        foreach ($resources as $resource) {
            $files->add($resource);
        }

        return $files->toArray();
    }

    /**
     * @inheritDoc
     */
    public function listDirectories(?string $path = null): array
    {
        $path = $this->normalizePath($path);

        $resources = $this->listResources($path);

        $directories = HashSet::of('string');
        foreach ($resources as $resource) {
            $relativePath = $resource;
            if ($path !== null) {
                $relativePath = ltrim(substr($resource, strlen($path)), '/');
            }
            if (!str_contains($relativePath, '/')) {
                continue;
            }
            $parts = explode('/', $relativePath);
            $directory = $path !== null
                ? sprintf('%s/%s', $path, $parts[0])
                : $parts[0];
            $directories->add($directory);
        }

        return $directories->toArray();
    }

    /**
     * @inheritDoc
     */
    public function listDirectoriesRecursively(?string $path = null): array
    {
        $path = $this->normalizePath($path);

        $resources = $this->listResources($path);

        $directories = HashSet::of('string');
        foreach ($resources as $resource) {
            $relativePath = $resource;
            if ($path !== null) {
                $relativePath = ltrim(substr($resource, strlen($path)), '/');
            }
            if (!str_contains($relativePath, '/')) {
                continue;
            }
            $parts = explode('/', $relativePath);
            $count = count($parts);
            for ($i = 0; $i < $count; $i++) {
                $dirs = array_slice($parts, 0, $i);
                $directory = $path !== null
                    ? sprintf('%s/%s', $path, implode('/', $dirs))
                    : implode('/', $dirs);
                if (empty($directory) || $directory === sprintf('%s/', $path)) {
                    continue;
                }
                $directories->add($directory);
            }
        }

        return $directories->toArray();
    }

    /**
     * Retrieves a list of resources for a given path
     *
     * @throws FileStorageException
     */
    protected function listResources(?string $path = null): array
    {
        $dirPath = sprintf('%s://%s', $this->protocol, $this->bucket);

        if (!empty($path)) {
            $dirPath = $this->getStreamPath(sprintf(
                '%s/',
                $this->normalizePath($path)
            ));
        }

        $handle = @opendir($dirPath);

        if ($handle === false) {
            $message = sprintf(
                'Unable to open directory for listing: %s',
                $dirPath
            );
            throw new FileStorageException($message);
        }

        $resources = [];
        while (($file = readdir($handle)) !== false) {
            $resources[] = $file;
        }

        closedir($handle);

        return $resources;
    }

    /**
     * Retrieves the stream path for the given file path
     */
    private function getStreamPath(string $filePath): string
    {
        return sprintf('%s://%s/%s', $this->protocol, $this->bucket, $filePath);
    }

    /**
     * Normalizes a path for GCP implementation
     */
    private function normalizePath(?string $path): ?string
    {
        if (empty($path) || $path === '/') {
            return null;
        }

        return trim($path, '/');
    }
}
