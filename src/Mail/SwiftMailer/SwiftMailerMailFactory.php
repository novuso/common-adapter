<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\SwiftMailer;

use Novuso\Common\Application\Mail\Message\Attachment;
use Novuso\Common\Application\Mail\Message\MailFactory;
use Novuso\Common\Application\Mail\Message\MailMessage;
use Swift_Attachment;

/**
 * Class SwiftMailerMailFactory
 */
final class SwiftMailerMailFactory implements MailFactory
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
        return SwiftMailerAttachment::fromString(
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
        return SwiftMailerAttachment::fromPath(
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
        return (new Swift_Attachment())->generateId();
    }
}
