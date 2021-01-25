<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\FileStorage;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemReader;
use League\Flysystem\FilesystemWriter;
use League\Flysystem\StorageAttributes;
use Novuso\Common\Application\FileStorage\Exception\FileStorageException;
use Novuso\Common\Application\FileStorage\FileStorage;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Throwable;

/**
 * Class FlysystemFileStorage
 */
final class FlysystemFileStorage implements FileStorage
{
    /**
     * Constructs FlysystemFileStorage
     */
    public function __construct(
        protected FilesystemReader $reader,
        protected FilesystemWriter $writer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function putFile(string $path, mixed $contents): void
    {
        if (is_string($contents)) {
            $stream = fopen('php://temp', 'r+');
            if (!empty($contents)) {
                fwrite($stream, $contents);
                fseek($stream, 0);
            }
            $contents = $stream;
        }

        try {
            $this->writer->writeStream($path, $contents);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }

        if (is_resource($contents)) {
            fclose($contents);
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileContents(string $path): string
    {
        try {
            $contents = $this->reader->read($path);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function getFileResource(string $path): mixed
    {
        try {
            $resource = $this->reader->readStream($path);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }

        return $resource;
    }

    /**
     * @inheritDoc
     */
    public function hasFile(string $path): bool
    {
        try {
            return $this->reader->fileExists($path);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function removeFile(string $path): void
    {
        try {
            $this->writer->delete($path);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function copyFile(string $source, string $destination): void
    {
        try {
            $this->writer->copy($source, $destination);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function moveFile(string $source, string $destination): void
    {
        try {
            $this->writer->move($source, $destination);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): int
    {
        try {
            $bytes = $this->reader->fileSize($path);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }

        return $bytes;
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): DateTime
    {
        try {
            $timestamp = $this->reader->lastModified($path);

            $dateTime = DateTime::fromTimestamp((int) $timestamp);
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }

    /**
     * @inheritDoc
     */
    public function listFiles(?string $path = null): array
    {
        try {
            $contents = $this->reader->listContents((string) $path);

            return $this->filterContentsListByType(
                $contents,
                StorageAttributes::TYPE_FILE
            );
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function listFilesRecursively(?string $path = null): array
    {
        try {
            $contents = $this->reader->listContents(
                (string) $path,
                $recursive = true
            );

            return $this->filterContentsListByType(
                $contents,
                StorageAttributes::TYPE_FILE
            );
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function listDirectories(?string $path = null): array
    {
        try {
            $contents = $this->reader->listContents((string) $path);

            return $this->filterContentsListByType(
                $contents,
                StorageAttributes::TYPE_DIRECTORY
            );
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function listDirectoriesRecursively(?string $path = null): array
    {
        try {
            $contents = $this->reader->listContents(
                (string) $path,
                $recursive = true
            );

            return $this->filterContentsListByType(
                $contents,
                StorageAttributes::TYPE_DIRECTORY
            );
        } catch (Throwable $e) {
            throw new FileStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Filters content list by type
     */
    protected function filterContentsListByType(
        DirectoryListing $list,
        string $type
    ): array {
        return $list
            ->filter(function (StorageAttributes $attributes) use ($type) {
                return $attributes->type() === $type;
            })
            ->map(function (StorageAttributes $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }
}
