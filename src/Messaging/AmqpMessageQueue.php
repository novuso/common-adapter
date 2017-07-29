<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging;

use Novuso\Common\Application\Messaging\MessageQueueInterface;
use Novuso\Common\Domain\Messaging\MessageInterface;
use Novuso\System\Serialization\SerializerInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * AmqpMessageQueue is an AMQP message queue
 *
 * @link      https://github.com/php-amqplib/php-amqplib AMQP Lib
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class AmqpMessageQueue implements MessageQueueInterface
{
    /**
     * Exchange name
     *
     * @var string
     */
    protected const EXCHANGE_NAME = 'message-queue';

    /**
     * Exchange type
     *
     * @var string
     */
    protected const EXCHANGE_TYPE = 'direct';

    /**
     * AMQP delivery tag
     *
     * @var string
     */
    protected const DELIVERY_TAG = 'delivery_tag';

    /**
     * Meta data sequence key
     *
     * @var string
     */
    protected const META_KEY = 'amqp_seq';

    /**
     * Passive setting
     *
     * @var bool
     */
    protected const PASSIVE = false;

    /**
     * Durable setting
     *
     * @var bool
     */
    protected const DURABLE = true;

    /**
     * Exclusive setting
     *
     * @var bool
     */
    protected const EXCLUSIVE = false;

    /**
     * Auto delete setting
     *
     * @var bool
     */
    protected const AUTO_DELETE = false;

    /**
     * AMQP connection
     *
     * @var AbstractConnection
     */
    protected $connection;

    /**
     * AMQP channel
     *
     * @var AMQPChannel|null
     */
    protected $channel;

    /**
     * Serializer
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * Message parameters
     *
     * @var array
     */
    protected $messageParams;

    /**
     * Constructs AmqpMessageQueue
     *
     * @param AbstractConnection  $connection The AMQP connection
     * @param SerializerInterface $serializer The serializer service
     */
    public function __construct(AbstractConnection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
        $this->messageParams = [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(string $topic, MessageInterface $message): void
    {
        $this->createTopic($topic);
        $amqpMessage = new AMQPMessage(
            $this->serializer->serialize($message),
            $this->messageParams
        );
        $this->getChannel()->basic_publish($amqpMessage, static::EXCHANGE_NAME, $topic);
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(string $topic): ?MessageInterface
    {
        $message = null;
        $this->createTopic($topic);
        $amqpMessage = $this->getChannel()->basic_get($topic);

        if ($amqpMessage) {
            /** @var MessageInterface $message */
            $message = $this->serializer->deserialize($amqpMessage->getBody());
            $message->metaData()->set(static::META_KEY, $amqpMessage->get(static::DELIVERY_TAG));
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(string $topic, MessageInterface $message): void
    {
        $this->getChannel()->basic_ack($message->metaData()->get(static::META_KEY));
    }

    /**
     * Creates topic if needed
     *
     * @param string $topic The topic name
     *
     * @return void
     */
    private function createTopic(string $topic): void
    {
        $channel = $this->getChannel();
        $channel->exchange_declare(
            static::EXCHANGE_NAME,
            static::EXCHANGE_TYPE,
            static::PASSIVE,
            static::DURABLE,
            static::AUTO_DELETE
        );
        $channel->queue_declare(
            $topic,
            static::PASSIVE,
            static::DURABLE,
            static::EXCLUSIVE,
            static::AUTO_DELETE
        );
        $channel->queue_bind(
            $topic,
            static::EXCHANGE_NAME,
            $topic
        );
    }

    /**
     * Retrieves the AMQP channel
     *
     * @return AMQPChannel
     */
    private function getChannel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->channel = $this->connection->channel();
        }

        return $this->channel;
    }
}
