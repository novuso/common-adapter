<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\Symfony;

use Exception;
use Novuso\Common\Application\Mail\Message\Attachment;
use Novuso\System\Exception\RuntimeException;

/**
 * Class SymfonyAttachment
 */
final class SymfonyAttachment implements Attachment
{
    /**
     * Constructs SymfonyAttachment
     *
     * @internal
     */
    protected function __construct(
        protected mixed $body,
        protected string $fileName,
        protected string $contentType,
        protected string $embedId,
        protected bool $inline
    ) {
    }

    /**
     * Creates instance from content string
     *
     * @throws Exception When an error occurs
     */
    public static function fromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): static {
        $inline = true;

        if ($embedId === null) {
            $embedId = bin2hex(random_bytes(16));
            $inline = false;
        }

        return new static($body, $fileName, $contentType, $embedId, $inline);
    }

    /**
     * Creates instance from a local file path
     *
     * @throws Exception When an error occurs
     */
    public static function fromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): SymfonyAttachment {
        $inline = true;

        if ($embedId === null) {
            $embedId = bin2hex(random_bytes(16));
            $inline = false;
        }

        $handle = @fopen($path, 'r', false);
        if ($handle === false) {
            $message = sprintf('Unable to open path: %s', $path);
            throw new RuntimeException($message);
        }

        return new static($handle, $fileName, $contentType, $embedId, $inline);
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->embedId;
    }

    /**
     * @inheritDoc
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * @inheritDoc
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @inheritDoc
     */
    public function getDisposition(): string
    {
        if ($this->inline) {
            return 'inline';
        }

        return 'attachment';
    }

    /**
     * @inheritDoc
     */
    public function embed(): string
    {
        return sprintf('cid:%s', $this->embedId);
    }
}
