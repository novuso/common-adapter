<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging;

use Novuso\Common\Application\Messaging\MessageQueueInterface;
use Novuso\Common\Domain\Messaging\MessageInterface;
use Novuso\System\Collection\LinkedQueue;

/**
 * InMemoryMessageQueue is an in memory message queue
 *
 * @link      https://github.com/php-amqplib/php-amqplib AMQP Lib
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class InMemoryMessageQueue implements MessageQueueInterface
{
    /**
     * Queues
     *
     * @var array
     */
    protected $queues = [];

    /**
     * Processing messages
     *
     * @var array
     */
    protected $processing = [];

    /**
     * {@inheritdoc}
     */
    public function enqueue(string $topic, MessageInterface $message): void
    {
        if (!isset($this->queues[$topic])) {
            $this->queues[$topic] = LinkedQueue::of(MessageInterface::class);
        }

        $this->queues[$topic]->enqueue($message);
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(string $topic): ?MessageInterface
    {
        if (!isset($this->queues[$topic])) {
            $this->queues[$topic] = LinkedQueue::of(MessageInterface::class);
        }

        $message = null;

        if (!$this->queues[$topic]->isEmpty()) {
            /** @var MessageInterface $message */
            $message = $this->queues[$topic]->dequeue();
            $hash = $message->hashValue();
            $this->processing[$hash] = [
                'timestamp' => time(),
                'message'   => $message
            ];
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(string $topic, MessageInterface $message): void
    {
        $hash = $message->hashValue();
        unset($this->processing[$hash]);
    }

    /**
     * Recycles dequeued messages that have not been acknowledged
     *
     * @param string $topic The topic name
     * @param int    $delay Number of seconds before message is recycled
     *
     * @return void
     */
    public function recycleMessages(string $topic, int $delay = 600): void
    {
        $time = time();

        foreach ($this->processing as $hash => $data) {
            $elapsed = $time - $data['timestamp'];
            if ($elapsed > $delay) {
                /** @var MessageInterface $message */
                $message = $data['message'];
                unset($this->processing[$hash]);
                $this->enqueue($topic, $message);
            }
        }
    }
}
