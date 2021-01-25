<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Sms\Twilio;

use Novuso\Common\Application\Sms\Exception\SmsException;
use Novuso\Common\Application\Sms\Message\SmsMessage;
use Novuso\Common\Application\Sms\Transport\SmsTransport;
use Novuso\Common\Domain\Value\Identifier\Url;
use Throwable;
use Twilio\Rest\Client;

/**
 * Class TwilioSmsTransport
 */
final class TwilioSmsTransport implements SmsTransport
{
    /**
     * Constructs TwilioSmsTransport
     */
    public function __construct(protected Client $client)
    {
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        try {
            $options = ['from' => $message->getFrom()];

            if ($message->getBody() !== null) {
                $options['body'] = $message->getBody();
            }

            $mediaUrls = [];
            /** @var Url $url */
            foreach ($message->getMedia() as $url) {
                $mediaUrls[] = $url->toString();
            }

            if (!empty($mediaUrls)) {
                if (count($mediaUrls) === 1) {
                    $options['mediaUrl'] = reset($mediaUrls);
                } else {
                    $options['mediaUrl'] = $mediaUrls;
                }
            }

            $this->client->messages->create($message->getTo(), $options);
        } catch (Throwable $e) {
            throw new SmsException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
