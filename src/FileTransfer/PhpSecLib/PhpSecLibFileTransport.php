<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\FileTransfer\PhpSecLib;

use Novuso\Common\Application\FileTransfer\Exception\FileTransferException;
use Novuso\Common\Application\FileTransfer\FileTransport;
use Novuso\Common\Application\FileTransfer\Resource;
use Novuso\Common\Application\FileTransfer\ResourceType;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\System\Exception\RuntimeException;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use Throwable;

/**
 * Class PhpSecLibFileTransport
 *
 * Requires installation of phpseclib version ~2.0
 *
 * @codeCoverageIgnore Requires SSH connection to test
 */
final class PhpSecLibFileTransport implements FileTransport
{
    protected RSA|string $secret;
    protected ?SFTP $connection = null;

    /**
     * Constructs PhpSecLibFileTransport
     */
    public function __construct(
        protected string $host,
        protected int $port,
        protected int $timeout,
        protected string $username,
        string $secret,
        bool $rsa
    ) {
        $this->timeout = abs($this->timeout);
        if ($rsa) {
            $this->secret = new RSA();
            $this->secret->loadKey(file_get_contents($secret));
        } else {
            $this->secret = $secret;
        }
    }

    /**
     * Handles PhpSecLibFileTransport destruct
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

        $temp = tmpfile();

        $bytes = @stream_copy_to_stream($contents, $temp);

        if ($bytes === false) {
            $message = 'Unable to copy contents to temporary file';
            throw new FileTransferException($message);
        }

        $tempPath = stream_get_meta_data($temp)['uri'];

        if (
            !$this->connection->put($path, $tempPath, SFTP::SOURCE_LOCAL_FILE)
        ) {
            $message = 'File SFTP send operation failed';
            throw new FileTransferException($message);
        }

        fclose($contents);
        fclose($temp);

        $this->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileContents(string $path): string
    {
        $this->connect();

        $tempPath = tempnam(sys_get_temp_dir(), 'phpseclib');

        if (!$this->connection->get($path, $tempPath)) {
            unlink($tempPath);
            $message = sprintf('Unable to retrieve file: %s', $path);
            throw new FileTransferException($message);
        }

        $contents = file_get_contents($tempPath);

        $this->disconnect();

        unlink($tempPath);

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileResource(string $path): mixed
    {
        $this->connect();

        $tempPath = tempnam(sys_get_temp_dir(), 'phpseclib');

        if (!$this->connection->get($path, $tempPath)) {
            unlink($tempPath);
            $message = sprintf('Unable to retrieve file: %s', $path);
            throw new FileTransferException($message);
        }

        $buffer = @fopen($tempPath, 'rb');

        if ($buffer === false) {
            $message = 'Unable to open temporary buffer file';
            throw new FileTransferException($message);
        }

        $temp = tmpfile();

        while ($line = fgets($buffer, 1024)) {
            fwrite($temp, $line, 1024);
        }
        fseek($temp, 0);

        $this->disconnect();

        fclose($buffer);
        unlink($tempPath);

        return $temp;
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(string $directory): iterable
    {
        $this->connect();

        $directory = rtrim($directory, '/');
        $list = $this->connection->rawlist($directory);

        if (!is_array($list)) {
            $message = sprintf(
                'SFTP error - unable to read contents of directory %s',
                $directory
            );
            throw new FileTransferException($message);
        }

        foreach ($list as $name => $data) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = sprintf('%s/%s', $directory, $name);

            $resourceType = match ((int) $data['type']) {
                1 => ResourceType::FILE(),
                2 => ResourceType::DIR(),
                3 => ResourceType::LINK(),
                default => ResourceType::UNKNOWN(),
            };

            yield new Resource(
                $path,
                (int) $data['size'],
                (int) $data['uid'],
                (int) $data['gid'],
                (int) $data['mode'],
                DateTime::fromTimestamp((int) $data['atime']),
                DateTime::fromTimestamp((int) $data['mtime']),
                $resourceType
            );
        }

        $this->disconnect();
    }

    /**
     * Retrieves the SFTP connection
     *
     * @throws FileTransferException When error occurs
     */
    protected function connect(): SFTP
    {
        try {
            if ($this->connection === null) {
                $this->connection = new SFTP(
                    $this->host,
                    $this->port,
                    $this->timeout
                );
                if (!$this->connection->login($this->username, $this->secret)) {
                    throw new RuntimeException('SFTP connection error');
                }
                // set timeout for long downloads/uploads
                $this->connection->setTimeout(86400);
            }

            return $this->connection;
        } catch (Throwable $e) {
            throw new FileTransferException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Closes SSH connection
     */
    protected function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }
}
