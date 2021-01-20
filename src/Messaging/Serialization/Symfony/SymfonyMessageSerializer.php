<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\Serialization\Symfony;

use Novuso\Common\Domain\Messaging\Message;
use Novuso\System\Exception\RuntimeException;
use Novuso\System\Serialization\Serializer;
use Novuso\System\Utility\ClassName;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

/**
 * Class SymfonyMessageSerializer
 */
final class SymfonyMessageSerializer implements SerializerInterface
{
    protected const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    /**
     * Constructs SymfonyJsonSerializer
     */
    public function __construct(protected Serializer $serializer)
    {
    }

    /**
     * @inheritDoc
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (
            empty($encodedEnvelope['body'])
            || empty($encodedEnvelope['headers'])
        ) {
            $message = 'Encoded envelope should have at least a "body" and some "headers"';
            throw new MessageDecodingFailedException($message);
        }

        $stamps = $this->decodeStamps($encodedEnvelope);
        $body = $encodedEnvelope['body'];

        try {
            $message = $this->serializer->deserialize($body);
        } catch (Throwable $e) {
            throw new MessageDecodingFailedException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        return new Envelope($message, $stamps);
    }

    /**
     * @inheritDoc
     *
     * @throws RuntimeException
     */
    public function encode(Envelope $envelope): array
    {
        $output = [];

        try {
            $envelope = $envelope->withoutStampsOfType(
                NonSendableStampInterface::class
            );

            /** @var Message $message */
            $message = $envelope->getMessage();

            $output['body'] = $this->serializer->serialize($message);

            foreach ($envelope->all() as $class => $stamps) {
                $name = sprintf(
                    '%s%s',
                    static::STAMP_HEADER_PREFIX,
                    ClassName::short($class)
                );
                $output['headers'][$name] = addslashes(serialize($stamps));
            }
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return $output;
    }

    /**
     * Decodes stamps for an encoded envelope
     *
     * @param array $encodedEnvelope The encoded envelope
     *
     * @return array
     *
     * @throws MessageDecodingFailedException
     */
    protected function decodeStamps(array $encodedEnvelope): array
    {
        $stamps = [];

        foreach ($encodedEnvelope['headers'] as $name => $value) {
            if (!str_starts_with($name, static::STAMP_HEADER_PREFIX)) {
                continue;
            }

            $data = $this->safelyUnserialize(stripslashes($value));

            foreach ($data as $stamp) {
                $stamps[] = $stamp;
            }
        }

        return $stamps;
    }

    /**
     * @codeCoverageIgnore
     */
    private function safelyUnserialize(string $contents)
    {
        $message = sprintf(
            'Could not decode message using PHP serialization: %s',
            $contents
        );
        $signalingException = new MessageDecodingFailedException($message);
        $prevUnserializeHandler = ini_set(
            'unserialize_callback_func',
            sprintf('%s::handleUnserializeCallback', self::class)
        );
        $prevErrorHandler = set_error_handler(
            function (
                $type,
                $msg,
                $file,
                $line,
                $context = []
            ) use (
                &$prevErrorHandler,
                $signalingException
            ) {
                if ($file === __FILE__) {
                    throw $signalingException;
                }

                return $prevErrorHandler ? $prevErrorHandler(
                    $type,
                    $msg,
                    $file,
                    $line,
                    $context
                ) : false;
            }
        );

        try {
            $meta = unserialize($contents);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }

        return $meta;
    }

    /**
     * @internal
     * @codeCoverageIgnore
     */
    public static function handleUnserializeCallback(string $class): void
    {
        $message = sprintf(
            'Message class "%s" not found during decoding',
            $class
        );

        throw new MessageDecodingFailedException($message);
    }
}
