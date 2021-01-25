<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Sms\Null;

use Novuso\Common\Application\Sms\Message\SmsMessage;
use Novuso\Common\Application\Sms\Transport\SmsTransport;

/**
 * Class NullSmsTransport
 */
final class NullSmsTransport implements SmsTransport
{
    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        // no operation
    }
}
