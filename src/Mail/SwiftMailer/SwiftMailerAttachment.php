<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\SwiftMailer;

use Novuso\Common\Application\Mail\Message\Attachment;
use Swift_Attachment;

/**
 * Class SwiftMailerAttachment
 */
final class SwiftMailerAttachment implements Attachment
{
    /**
     * Constructs SwiftMailerAttachment
     *
     * @internal
     */
    protected function __construct(protected Swift_Attachment $attachment)
    {
    }

    /**
     * Creates instance from content string
     */
    public static function fromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): static {
        $attachment = new Swift_Attachment($body, $fileName, $contentType);

        if ($embedId !== null) {
            $attachment->setId($embedId);
            $attachment->setDisposition('inline');
        }

        return new static($attachment);
    }

    /**
     * Creates instance from a local file path
     */
    public static function fromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): static {
        $attachment = Swift_Attachment::fromPath($path, $contentType)
            ->setFilename($fileName);

        if ($embedId !== null) {
            $attachment->setId($embedId);
            $attachment->setDisposition('inline');
        }

        return new static($attachment);
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->attachment->getId();
    }

    /**
     * @inheritDoc
     */
    public function getBody(): mixed
    {
        return $this->attachment->getBody();
    }

    /**
     * @inheritDoc
     */
    public function getFileName(): string
    {
        return $this->attachment->getFilename();
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return $this->attachment->getContentType();
    }

    /**
     * @inheritDoc
     */
    public function getDisposition(): string
    {
        return $this->attachment->getDisposition();
    }

    /**
     * @inheritDoc
     */
    public function embed(): string
    {
        return sprintf('cid:%s', $this->attachment->getId());
    }
}
