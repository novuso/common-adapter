<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\MessageQueue;

use Novuso\Common\Application\Messaging\Exception\MessageQueueException;
use Novuso\Common\Application\Messaging\MessageQueue;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\System\Collection\LinkedQueue;
use Novuso\System\Collection\Type\Queue;

/**
 * Class InMemoryMessageQueue
 */
final class InMemoryMessageQueue implements MessageQueue
{
    protected array $queues = [];
    protected array $processing = [];

    /**
     * @inheritDoc
     */
    public function enqueue(string $name, Message $message): void
    {
        $this->getQueue($name)->enqueue($message);
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
        $message = null;
        $queue = $this->getQueue($name);

        if (!$queue->isEmpty()) {
            /** @var Message $message */
            $message = $queue->dequeue();
            $hash = $message->hashValue();
            $this->processing[$hash] = [
                'timestamp' => time(),
                'message'   => $message
            ];
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function acknowledge(string $name, Message $message): void
    {
        $hash = $message->hashValue();
        unset($this->processing[$hash]);
    }

    /**
     * @inheritDoc
     */
    public function reject(
        string $name,
        Message $message,
        bool $requeue = false
    ): void {
        $this->acknowledge($name, $message);

        if ($requeue) {
            $this->enqueue($name, $message);
        }
    }

    /**
     * Recycles dequeued messages that have not been acknowledged
     *
     * @throws MessageQueueException When an error occurs
     */
    public function recycleMessages(string $name, int $delay = 600): void
    {
        $timestamp = time();

        $marked = [];
        foreach ($this->processing as $hash => $data) {
            $elapsed = $timestamp - $data['timestamp'];
            if ($elapsed > $delay) {
                /** @var Message $message */
                $message = $data['message'];
                $this->enqueue($name, $message);
                $marked[] = $hash;
            }
        }

        foreach ($marked as $hash) {
            unset($this->processing[$hash]);
        }
    }

    /**
     * Retrieves a queue by name
     */
    protected function getQueue(string $name): Queue
    {
        if (!isset($this->queues[$name])) {
            $this->queues[$name] = LinkedQueue::of(Message::class);
        }

        return $this->queues[$name];
    }
}
