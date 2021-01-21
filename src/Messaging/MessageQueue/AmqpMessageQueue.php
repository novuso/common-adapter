<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\MessageQueue;

use Novuso\Common\Application\Messaging\Exception\MessageQueueException;
use Novuso\Common\Application\Messaging\MessageQueue;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\System\Exception\DomainException;
use Novuso\System\Serialization\Serializer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Class AmqpMessageQueue
 */
final class AmqpMessageQueue implements MessageQueue
{
    protected const EXCHANGE_NAME = 'message-queue';
    protected const EXCHANGE_TYPE = 'direct';
    protected const DELIVERY_TAG = 'delivery_tag';
    protected const META_KEY = 'amqp_seq';
    protected const PASSIVE = false;
    protected const DURABLE = true;
    protected const EXCLUSIVE = false;
    protected const AUTO_DELETE = false;

    protected ?AMQPChannel $channel = null;
    protected array $messageParams = [
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
    ];
    protected array $bound = [];

    /**
     * Constructs AmqpMessageQueue
     */
    public function __construct(
        protected AbstractConnection $connection,
        protected Serializer $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function enqueue(string $name, Message $message): void
    {
        try {
            $this->createQueue($name);

            $amqpMessage = new AMQPMessage(
                $this->serializer->serialize($message),
                $this->messageParams
            );

            $this->getChannel()->basic_publish(
                $amqpMessage,
                static::EXCHANGE_NAME,
                $name
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
        $start = time();
        $message = null;

        while (true) {
            $message = $this->dequeueNonBlocking($name);
            if ($message !== null) {
                break;
            }
            $current = time();
            $elapsed = $current - $start;
            if ($timeout !== 0 && $elapsed > $timeout) {
                break;
            }
            sleep(1);
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function dequeueNonBlocking(string $name): ?Message
    {
        try {
            $message = null;

            $this->createQueue($name);

            /** @var AMQPMessage $amqpMessage */
            $amqpMessage = $this->getChannel()->basic_get($name);

            if ($amqpMessage) {
                /** @var Message $message */
                $message = $this->serializer->deserialize(
                    $amqpMessage->getBody()
                );
                $message->metaData()->set(
                    static::META_KEY,
                    $amqpMessage->get(static::DELIVERY_TAG)
                );
            }

            return $message;
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
    public function acknowledge(string $name, Message $message): void
    {
        try {
            if (!$message->metaData()->has(static::META_KEY)) {
                $message = sprintf(
                    'Message meta data missing %s',
                    static::META_KEY
                );
                throw new DomainException($message);
            }

            $this->getChannel()->basic_ack(
                $message->metaData()->get(static::META_KEY)
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
    public function reject(
        string $name,
        Message $message,
        bool $requeue = false
    ): void {
        try {
            if (!$message->metaData()->has(static::META_KEY)) {
                $message = sprintf(
                    'Message meta data missing %s',
                    static::META_KEY
                );
                throw new DomainException($message);
            }

            $this->getChannel()->basic_reject(
                $message->metaData()->get(static::META_KEY),
                $requeue
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
     * Creates a queue
     */
    protected function createQueue(string $name): void
    {
        if (isset($this->bound[$name])) {
            return;
        }

        $channel = $this->getChannel();

        $channel->exchange_declare(
            static::EXCHANGE_NAME,
            static::EXCHANGE_TYPE,
            static::PASSIVE,
            static::DURABLE,
            static::AUTO_DELETE
        );

        $channel->queue_declare(
            $name,
            static::PASSIVE,
            static::DURABLE,
            static::EXCLUSIVE,
            static::AUTO_DELETE
        );

        $channel->queue_bind(
            $name,
            static::EXCHANGE_NAME,
            $name
        );

        $this->bound[$name] = true;
    }

    /**
     * Retrieves the AMQP channel
     */
    protected function getChannel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->channel = $this->connection->channel();
        }

        return $this->channel;
    }
}
