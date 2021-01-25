<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\FileTransfer\Ftp;

use Novuso\Common\Application\FileTransfer\Exception\FileTransferException;
use Novuso\Common\Application\FileTransfer\FileTransport;
use Novuso\Common\Application\FileTransfer\Resource;
use Novuso\Common\Application\FileTransfer\ResourceType;
use Novuso\Common\Domain\Value\DateTime\DateTime;

/**
 * Class FtpFileTransport
 *
 * Requires installation of the FTP extension and libssl
 *
 * @codeCoverageIgnore Requires FTP connection to test
 */
final class FtpFileTransport implements FileTransport
{
    protected mixed $connection;

    /**
     * Constructs FtpFileTransport
     */
    public function __construct(
        protected string $host,
        protected int $port,
        protected string $username = 'anonymous',
        protected string $password = '',
        protected bool $ssl = false,
        protected int $timeout = 90,
        protected bool $passive = false
    ) {
    }

    /**
     * Handles FtpFileTransport destruct
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function sendFile(string $path, mixed $contents): void
    {
        $this->connect();

        if (is_string($contents)) {
            $stream = fopen('php://temp', 'rb+');
            if (!empty($contents)) {
                fwrite($stream, $contents);
                fseek($stream, 0);
            }
            $contents = $stream;
        }

        if (!$this->isDirectory(dirname($path))) {
            $this->makeDirectory(dirname($path));
        }

        $success = @ftp_fput($this->connection, $path, $contents, FTP_BINARY);

        if (!$success) {
            $message = sprintf('Unable to send file to path %s', $path);
            throw new FileTransferException($message);
        }

        fclose($contents);

        $this->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileContents(string $path): string
    {
        $this->connect();

        $handle = fopen('php://temp', 'rb+');

        $success = @ftp_fget($this->connection, $handle, $path, FTP_BINARY);

        if (!$success) {
            $message = sprintf('Unable to retrieve file at path %s', $path);
            throw new FileTransferException($message);
        }

        rewind($handle);

        $contents = @stream_get_contents($handle);

        if ($contents === false) {
            $message = sprintf('Unable to retrieve file at path %s', $path);
            throw new FileTransferException($message);
        }

        $this->disconnect();

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileResource(string $path): mixed
    {
        $this->connect();

        $handle = tmpfile();

        $success = @ftp_fget($this->connection, $handle, $path, FTP_BINARY);

        if (!$success) {
            $message = sprintf('Unable to retrieve file at path %s', $path);
            throw new FileTransferException($message);
        }

        rewind($handle);

        $this->disconnect();

        return $handle;
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(string $directory): iterable
    {
        $this->connect();

        $directory = rtrim($directory, '/');
        if (!$this->isDirectory($directory)) {
            $message = sprintf('Directory does not exist: %s', $directory);
            throw new FileTransferException($message);
        }

        $resourceData = @ftp_rawlist($this->connection, $directory);

        foreach ($resourceData as $index => $line) {
            $chunks = preg_split('/\s+/', $line, 9);

            if (
                isset($chunks[8])
                && ($chunks[8] === '.' || $chunks[8] === '..')
            ) {
                continue;
            }

            $permissionString = $chunks[0];
            $resourceType = $this->permissionsToType($permissionString);

            $path = sprintf('%s/%s', $directory, $chunks[8]);

            if ($resourceType->equals(ResourceType::LINK())) {
                $parts = explode(' ', $chunks[8]);
                $path = sprintf('%s/%s', $directory, $parts[0]);
            }

            $size = (int) $chunks[4];
            $userId = 0;
            $groupId = 0;
            $mode = $this->permissionsToMode($permissionString);
            $accessTime = DateTime::fromFormat(
                'M d H:i',
                sprintf('%s %s %s', $chunks[5], $chunks[6], $chunks[7])
            );
            $modifyTime = DateTime::fromFormat(
                'M d H:i',
                sprintf('%s %s %s', $chunks[5], $chunks[6], $chunks[7])
            );

            yield new Resource(
                $path,
                $size,
                $userId,
                $groupId,
                $mode,
                $accessTime,
                $modifyTime,
                $resourceType
            );
        }

        $this->disconnect();
    }

    /**
     * Retrieves the FTP connection
     *
     * @return resource
     *
     * @throws FileTransferException When error occurs
     */
    protected function connect(): mixed
    {
        if ($this->connection === null) {
            if ($this->ssl) {
                $connection = @ftp_ssl_connect(
                    $this->host,
                    $this->port,
                    $this->timeout
                );
            } else {
                $connection = @ftp_connect(
                    $this->host,
                    $this->port,
                    $this->timeout
                );
            }

            if ($connection === false) {
                $message = sprintf(
                    'Unable to connect to host %s port %d',
                    $this->host,
                    $this->port
                );
                throw new FileTransferException($message);
            }

            $success = @ftp_login(
                $connection,
                $this->username,
                $this->password
            );

            if (!$success) {
                $message = 'FTP authentication failed';
                throw new FileTransferException($message);
            }

            if ($this->passive) {
                $success = @ftp_pasv($connection, $passive = true);
                if (!$success) {
                    $message = 'Failed to set FTP passive mode';
                    throw new FileTransferException($message);
                }
            }

            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * Closes FTP connection
     */
    protected function disconnect(): void
    {
        if ($this->connection !== null) {
            ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Retrieves the mode
     */
    private function permissionsToMode(string $permissions): int
    {
        $mode = 0;

        if ($permissions[1] == 'r') {
            $mode += 0400;
        }
        if ($permissions[2] == 'w') {
            $mode += 0200;
        }
        if ($permissions[3] == 'x') {
            $mode += 0100;
        } elseif ($permissions[3] == 's') {
            $mode += 04100;
        } elseif ($permissions[3] == 'S') {
            $mode += 04000;
        }
        if ($permissions[4] == 'r') {
            $mode += 040;
        }
        if ($permissions[5] == 'w') {
            $mode += 020;
        }
        if ($permissions[6] == 'x') {
            $mode += 010;
        } elseif ($permissions[6] == 's') {
            $mode += 02010;
        } elseif ($permissions[6] == 'S') {
            $mode += 02000;
        }
        if ($permissions[7] == 'r') {
            $mode += 04;
        }
        if ($permissions[8] == 'w') {
            $mode += 02;
        }
        if ($permissions[9] == 'x') {
            $mode += 01;
        } elseif ($permissions[9] == 't') {
            $mode += 01001;
        } elseif ($permissions[9] == 'T') {
            $mode += 01000;
        }

        return $mode;
    }

    /**
     * Retrieves the resource type
     */
    private function permissionsToType(string $permissions): ResourceType
    {
        return match ($permissions[0]) {
            '-' => ResourceType::FILE(),
            'd' => ResourceType::DIR(),
            'l' => ResourceType::LINK(),
            default => ResourceType::UNKNOWN(),
        };
    }

    /**
     * Creates a directory
     *
     * @throws FileTransferException
     */
    private function makeDirectory(string $path, bool $recursive = true): void
    {
        if (!$recursive || $this->isDirectory($path)) {
            $success = @ftp_mkdir($this->connection, $path);
            if (!$success) {
                $message = sprintf('Unable to make directory: %s', $path);
                throw new FileTransferException($message);
            }

            return;
        }

        $pwd = ftp_pwd($this->connection);
        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (!@ftp_chdir($this->connection, $part)) {
                $success = @ftp_mkdir($this->connection, $part);
                if (!$success) {
                    $message = sprintf('Unable to make directory: %s', $path);
                    throw new FileTransferException($message);
                }
                ftp_chdir($this->connection, $part);
            }
        }

        ftp_chdir($this->connection, $pwd);
    }

    /**
     * Checks if a given path is a directory
     *
     * @throws FileTransferException
     */
    private function isDirectory(string $path): bool
    {
        $pwd = @ftp_pwd($this->connection);

        if ($pwd === false) {
            $message = 'Unable to resolve the current directory';
            throw new FileTransferException($message);
        }

        if (@ftp_chdir($this->connection, $path)) {
            ftp_chdir($this->connection, $pwd);

            return true;
        }

        ftp_chdir($this->connection, $pwd);

        return false;
    }
}
