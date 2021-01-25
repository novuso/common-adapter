<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Mail\Null;

use Novuso\Common\Application\Mail\Message\MailMessage;
use Novuso\Common\Application\Mail\Transport\MailTransport;

/**
 * Class NullMailTransport
 */
final class NullMailTransport implements MailTransport
{
    /**
     * @inheritDoc
     */
    public function send(MailMessage $message): void
    {
        // no operation
    }
}
