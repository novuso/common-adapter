<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Sms\Logging;

use Novuso\Common\Application\Sms\Message\SmsMessage;
use Novuso\Common\Application\Sms\Transport\SmsTransport;
use Novuso\Common\Domain\Value\Identifier\Url;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggingSmsTransport
 */
final class LoggingSmsTransport implements SmsTransport
{
    /**
     * Constructs LoggingSmsTransport
     */
    public function __construct(
        protected SmsTransport $transport,
        protected LoggerInterface $logger,
        protected string $logLevel = LogLevel::DEBUG
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        $this->logger->log(
            $this->logLevel,
            '[SMS]: Outgoing SMS Message',
            [
                'to'    => $message->getTo(),
                'from'  => $message->getFrom(),
                'body'  => $message->getBody(),
                'media' => array_map(function (Url $url) {
                    return $url->toString();
                }, $message->getMedia())
            ]
        );

        $this->transport->send($message);
    }
}
