<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\MessageQueue;

use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Novuso\Common\Application\Messaging\Exception\MessageQueueException;
use Novuso\Common\Application\Messaging\MessageQueue;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\Common\Domain\Messaging\MetaData;
use Novuso\System\Exception\DomainException;
use Novuso\System\Serialization\Serializer;
use Throwable;

/**
 * Class QueueInteropMessageQueue
 */
final class QueueInteropMessageQueue implements MessageQueue
{
    protected const META_KEY = 'queue_interop';

    /**
     * Constructs QueueInteropMessageQueue
     */
    public function __construct(
        protected Context $context,
        protected Serializer $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function enqueue(string $name, Message $message): void
    {
        $producer = $this->context->createProducer();
        $queue = $this->context->createQueue($name);

        try {
            $producer->send(
                $queue,
                $this->context->createMessage(
                    $this->serializer->serialize($message)
                )
            );
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function dequeue(string $name, int $timeout = 0): ?Message
    {
        $queue = $this->context->createQueue($name);
        $consumer = $this->context->createConsumer($queue);

        $queueMessage = $consumer->receive($timeout * 1000);

        if ($queueMessage === null) {
            return null;
        }

        try {
            /** @var Message $message */
            $message = $this->serializer->deserialize($queueMessage->getBody());
            $message = $message->withMetaData(
                $this->createMetaData($queueMessage)
            );
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function dequeueNonBlocking(string $name): ?Message
    {
        $queue = $this->context->createQueue($name);
        $consumer = $this->context->createConsumer($queue);

        $queueMessage = $consumer->receiveNoWait();

        if ($queueMessage === null) {
            return null;
        }

        try {
            /** @var Message $message */
            $message = $this->serializer->deserialize($queueMessage->getBody());
            $message = $message->withMetaData(
                $this->createMetaData($queueMessage)
            );
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function acknowledge(string $name, Message $message): void
    {
        $queue = $this->context->createQueue($name);
        $consumer = $this->context->createConsumer($queue);

        try {
            $consumer->acknowledge($this->createQueueMessage($message));
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function reject(string $name, Message $message, bool $requeue = false): void
    {
        $queue = $this->context->createQueue($name);
        $consumer = $this->context->createConsumer($queue);

        try {
            $consumer->reject($this->createQueueMessage($message), $requeue);
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Creates MetaData instance
     *
     * @throws DomainException
     */
    protected function createMetaData(QueueMessage $queueMessage): MetaData
    {
        return MetaData::create([
            static::META_KEY => [
                'properties' => $queueMessage->getProperties(),
                'headers'    => $queueMessage->getHeaders()
            ]
        ]);
    }

    /**
     * Creates QueueMessage instance
     *
     * @throws DomainException
     */
    protected function createQueueMessage(Message $message): QueueMessage
    {
        if (!$message->metaData()->has(static::META_KEY)) {
            $message = sprintf(
                'Message meta data missing %s',
                static::META_KEY
            );
            throw new DomainException($message);
        }

        $body = $this->serializer->serialize($message);
        $data = $message->metaData()->get(static::META_KEY);
        $properties = $data['properties'];
        $headers = $data['headers'];

        return $this->context->createMessage(
            $body,
            $properties,
            $headers
        );
    }
}
