<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\MessageQueue;

use Novuso\Common\Application\Messaging\Exception\MessageQueueException;
use Novuso\Common\Application\Messaging\MessageQueue;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\System\Exception\DomainException;
use Novuso\System\Serialization\Serializer;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Job;
use Throwable;

/**
 * Class PheanstalkMessageQueue
 */
final class PheanstalkMessageQueue implements MessageQueue
{
    protected const META_KEY = 'beanstalk_id';

    /**
     * Constructs PheanstalkMessageQueue
     */
    public function __construct(
        protected PheanstalkInterface $pheanstalk,
        protected Serializer $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function enqueue(string $name, Message $message): void
    {
        try {
            if ($this->pheanstalk->listTubeUsed() !== $name) {
                $this->pheanstalk->useTube($name);
            }

            $this->pheanstalk->put($this->serializer->serialize($message));
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

            /** @var Job|null $job */
            $job = $this->pheanstalk->watchOnly($name)->reserveWithTimeout(0);

            if ($job) {
                /** @var Message $message */
                $message = $this->serializer->deserialize($job->getData());
                $message->metaData()->set(static::META_KEY, $job->getId());
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

            $job = new Job($message->metaData()->get(static::META_KEY), '');

            $this->pheanstalk->delete($job);
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
            if ($requeue) {
                $job = new Job($message->metaData()->get(static::META_KEY), '');

                $this->pheanstalk->release($job);

                return;
            }

            $this->acknowledge($name, $message);
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
