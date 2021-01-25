<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\Symfony;

use Novuso\Common\Application\Mail\Message\Attachment;
use Novuso\Common\Application\Mail\Message\MailFactory;
use Novuso\Common\Application\Mail\Message\MailMessage;

/**
 * Class SymfonyMailFactory
 */
final class SymfonyMailFactory implements MailFactory
{
    /**
     * @inheritDoc
     */
    public function createMessage(): MailMessage
    {
        return MailMessage::create();
    }

    /**
     * @inheritDoc
     */
    public function createAttachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return SymfonyAttachment::fromString(
            $body,
            $fileName,
            $contentType,
            $embedId
        );
    }

    /**
     * @inheritDoc
     */
    public function createAttachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return SymfonyAttachment::fromPath(
            $path,
            $fileName,
            $contentType,
            $embedId
        );
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
