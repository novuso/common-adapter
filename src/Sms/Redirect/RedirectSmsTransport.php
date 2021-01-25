<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Sms\Redirect;

use Novuso\Common\Application\Sms\Message\SmsMessage;
use Novuso\Common\Application\Sms\Transport\SmsTransport;

/**
 * Class RedirectSmsTransport
 */
final class RedirectSmsTransport implements SmsTransport
{
    /**
     * Constructs RedirectSmsTransport
     */
    public function __construct(
        protected SmsTransport $transport,
        protected string $phoneNumber
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        $this->transport->send($this->createRedirectMessage($message));
    }

    /**
     * Creates a redirect SMS message
     */
    protected function createRedirectMessage(SmsMessage $message): SmsMessage
    {
        $redirect = SmsMessage::create(
            $this->phoneNumber,
            $message->getFrom()
        );

        if ($message->getBody() !== null) {
            $redirect->setBody($message->getBody());
        }

        foreach ($message->getMedia() as $url) {
            $redirect->addMedia($url);
        }

        return $redirect;
    }
}
