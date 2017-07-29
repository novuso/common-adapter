<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging;

use Novuso\Common\Application\Messaging\MessageQueueInterface;
use Novuso\Common\Domain\Messaging\MessageInterface;
use Novuso\System\Serialization\SerializerInterface;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;

/**
 * BeanstalkdMessageQueue is an beanstalk message queue
 *
 * @link      https://github.com/pda/pheanstalk Pheanstalk
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class BeanstalkdMessageQueue implements MessageQueueInterface
{
    /**
     * Meta data sequence key
     *
     * @var string
     */
    protected const META_KEY = 'beanstalk_id';

    /**
     * Pheanstalk
     *
     * @var PheanstalkInterface
     */
    protected $pheanstalk;

    /**
     * Serializer
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * Constructs BeanstalkdMessageQueue
     *
     * @param PheanstalkInterface $pheanstalk The pheanstalk instance
     * @param SerializerInterface $serializer The serializer service
     */
    public function __construct(PheanstalkInterface $pheanstalk, SerializerInterface $serializer)
    {
        $this->pheanstalk = $pheanstalk;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(string $topic, MessageInterface $message): void
    {
        $this->pheanstalk->putInTube($topic, $this->serializer->serialize($message));
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(string $topic): ?MessageInterface
    {
        /** @var Job|null $job */
        $job = $this->pheanstalk->reserveFromTube($topic);

        $message = null;

        if ($job) {
            /** @var MessageInterface $message */
            $message = $this->serializer->deserialize($job->getData());
            $message->metaData()->set(static::META_KEY, $job->getId());
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(string $topic, MessageInterface $message): void
    {
        $job = new Job($message->metaData()->get(static::META_KEY), '');

        $this->pheanstalk->delete($job);
    }
}
