<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\FileStorage;

use Aws\S3\S3Client;
use Novuso\Common\Application\FileStorage\Exception\FileStorageException;
use Novuso\Common\Application\FileStorage\FileStorage;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Throwable;

use function Aws\recursive_dir_iterator;

/**
 * Class S3FileStorage
 *
 * @codeCoverageIgnore Stream wrapper dictates use of native PHP filesystem functions
 */
final class S3FileStorage implements FileStorage
{
    /**
     * Constructs S3FileStorage
     */
    public function __construct(
        protected S3Client $client,
        protected string $bucket
    ) {
        $this->client->registerStreamWrapper();
    }

    /**
     * @inheritDoc
     */
    public function putFile(string $path, mixed $contents): void
    {
        $streamPath = $this->getStreamPath($path);
        $handle = @fopen($streamPath, 'wb');

        if ($handle === false) {
            $message = sprintf('Unable to write file: %s', $path);
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

        $bytes = @stream_copy_to_stream($contents, $handle);

        if ($bytes === false) {
            $message = sprintf('Unable to copy contents to file: %s', $path);
            throw new FileStorageException($message);
        }

        fclose($contents);
        fclose($handle);
    }

    /**
     * @inheritDoc
     */
    public function getFileContents(string $path): string
    {
        $context = stream_context_create(['s3' => ['seekable' => true]]);
        $streamPath = $this->getStreamPath($path);
        $handle = @fopen($streamPath, 'rb', $useIncludePath = false, $context);

        if ($handle === false) {
            $message = sprintf('Unable to open file for reading: %s', $path);
            throw new FileStorageException($message);
        }

        $contents = @stream_get_contents($handle);

        if ($contents === false) {
            $message = sprintf('Error reading file: %s', $path);
            throw new FileStorageException($message);
        }

        fclose($handle);

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function getFileResource(string $path): mixed
    {
        $context = stream_context_create(['s3' => ['seekable' => true]]);
        $streamPath = $this->getStreamPath($path);
        $handle = @fopen($streamPath, 'rb', $useIncludePath = false, $context);

        if ($handle === false) {
            $message = sprintf('Unable to open file for reading: %s', $path);
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
        if ($this->hasFile($path)) {
            $success = @unlink($this->getStreamPath($path));
            if (!$success) {
                $message = sprintf('Unable to remove file: %s', $path);
                throw new FileStorageException($message);
            }
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

        try {
            // allows prefix dir in bucket name
            $bucket = $this->bucket;
            if (str_contains($this->bucket, '/')) {
                [$bucket, $prefix] = explode('/', $this->bucket, 2);
                $source = sprintf('%s/%s', $prefix, $source);
                $destination = sprintf('%s/%s', $prefix, $destination);
            }
            $this->client->copyObject([
                'Bucket'     => $bucket,
                'Key'        => $destination,
                'CopySource' => sprintf('%s/%s', $bucket, $source)
            ]);
        } catch (Throwable $e) {
            $message = sprintf(
                'Unable to copy file %s to %s (%s)',
                $source,
                $destination,
                $e->getMessage()
            );
            throw new FileStorageException($message, $e->getCode(), $e);
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

        if (!$success) {
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
            $message = sprintf('Unable to get size of %s', $path);
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

        $modifiedTime = @filemtime($this->getStreamPath($path));

        if ($modifiedTime === false) {
            $message = sprintf('Unable to get last modified time of %s', $path);
            throw new FileStorageException($message);
        }

        return DateTime::fromTimestamp($modifiedTime);
    }

    /**
     * @inheritDoc
     */
    public function listFiles(?string $path = null): array
    {
        $resources = $this->listResources($path);

        return $resources['file'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function listFilesRecursively(?string $path = null): array
    {
        $resources = $this->listResourcesRecursively($path);

        return $resources['file'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function listDirectories(?string $path = null): array
    {
        $resources = $this->listResources($path);

        return $resources['dir'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function listDirectoriesRecursively(?string $path = null): array
    {
        $resources = $this->listResourcesRecursively($path);

        return $resources['dir'] ?? [];
    }

    /**
     * Retrieves the stream path
     */
    protected function getStreamPath(string $path): string
    {
        return sprintf('s3://%s/%s', $this->bucket, $path);
    }

    /**
     * Lists resources
     *
     * @throws FileStorageException When error occurs
     */
    protected function listResources(?string $path = null): array
    {
        $dirPath = sprintf('s3://%s', $this->bucket);

        if ($path !== null) {
            $dirPath = $this->getStreamPath($path);
        }

        if (!is_dir($dirPath)) {
            $message = sprintf('Directory not found: %s', $dirPath);
            throw new FileStorageException($message);
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
            $filePath = sprintf('%s/%s', rtrim($dirPath, '/'), $file);
            $fileType = filetype($filePath);
            $relativePath = str_replace(
                sprintf('s3://%s/', $this->bucket),
                '',
                $filePath
            );
            $resources[$fileType][] = $relativePath;
        }

        closedir($handle);

        return $resources;
    }

    /**
     * Lists resources recursively
     *
     * @throws FileStorageException When error occurs
     */
    protected function listResourcesRecursively(?string $path = null): array
    {
        $dirPath = sprintf('s3://%s', $this->bucket);

        if ($path !== null) {
            $dirPath = $this->getStreamPath($path);
        }

        if (!is_dir($dirPath)) {
            $message = sprintf('Directory not found: %s', $dirPath);
            throw new FileStorageException($message);
        }

        $iterator = recursive_dir_iterator($dirPath);
        $resources = [];
        foreach ($iterator as $filePath) {
            $fileType = filetype($filePath);
            $relativePath = str_replace(
                sprintf('s3://%s/', $this->bucket),
                '',
                $filePath
            );
            $resources[$fileType][] = $relativePath;
        }

        return $resources;
    }
}
