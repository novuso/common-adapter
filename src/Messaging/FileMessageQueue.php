<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging;

use FilesystemIterator;
use GlobIterator;
use Novuso\Common\Application\Filesystem\FilesystemInterface;
use Novuso\Common\Application\Messaging\MessageQueueInterface;
use Novuso\Common\Domain\Messaging\MessageInterface;
use Novuso\System\Serialization\SerializerInterface;

/**
 * FileMessageQueue is a filesystem backed message queue
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class FileMessageQueue implements MessageQueueInterface
{
    /**
     * Filesystem
     *
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * Serializer
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * Base directory
     *
     * @var string
     */
    protected $baseDir;

    /**
     * File permissions
     *
     * @var int
     */
    protected $permissions;

    /**
     * Constructs FileMessageQueue
     *
     * @param FilesystemInterface $filesystem  The filesystem service
     * @param SerializerInterface $serializer  The serializer service
     * @param string              $baseDir     The base queue directory
     * @param int                 $permissions The file permissions
     */
    public function __construct(
        FilesystemInterface $filesystem,
        SerializerInterface $serializer,
        string $baseDir,
        int $permissions = 0640
    ) {
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
        $this->baseDir = $baseDir;
        $this->permissions = $permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(string $topic, MessageInterface $message): void
    {
        $this->createTopic($topic);
        $directory = $this->getTopicDirectory($topic);
        $filename = $this->getMessageFileName($message);
        $content = $this->serializer->serialize($message);
        $path = sprintf('%s%s%s', $directory, DIRECTORY_SEPARATOR, $filename);
        $this->filesystem->put($path, $content);
        $this->filesystem->chmod($path, $this->permissions);
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(string $topic): ?MessageInterface
    {
        $this->createTopic($topic);
        $directory = $this->getTopicDirectory($topic);

        $filePattern = sprintf('%s%s*.message', $directory, DIRECTORY_SEPARATOR);
        $iterator = new GlobIterator($filePattern, FilesystemIterator::KEY_AS_FILENAME);
        $files = array_keys(iterator_to_array($iterator));

        $message = null;

        if ($files) {
            $fileName = array_pop($files);
            $realPath = sprintf('%s%s%s', $directory, DIRECTORY_SEPARATOR, $fileName);
            $content = $this->filesystem->get($realPath);
            /** @var MessageInterface $message */
            $message = $this->serializer->deserialize($content);
            $targetPath = str_replace('message', 'process', $realPath);
            $this->filesystem->rename($realPath, $targetPath);
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(string $topic, MessageInterface $message): void
    {
        $this->createTopic($topic);
        $directory = $this->getTopicDirectory($topic);
        $fileName = $this->getMessageFileName($message);
        $processFile = str_replace('message', 'process', $fileName);
        $path = sprintf('%s%s%s', $directory, DIRECTORY_SEPARATOR, $processFile);

        if (!$this->filesystem->isFile($path)) {
            return;
        }

        $this->filesystem->remove($path);
    }

    /**
     * Recycles dequeued messages that have not been acknowledged
     *
     * When using the FileMessageQueue, you should have a separate script call
     * this method regularly. For instance, you might want a cron job to run
     * every few minutes depending on your needs.
     *
     * @param string $topic The topic name
     * @param int    $delay Number of seconds before message is recycled
     *
     * @return void
     */
    public function recycleMessages(string $topic, int $delay = 600): void
    {
        $time = time();
        $this->createTopic($topic);
        $directory = $this->getTopicDirectory($topic);

        $filePattern = sprintf('%s%s*.process', $directory, DIRECTORY_SEPARATOR);
        $iterator = new GlobIterator($filePattern, FilesystemIterator::KEY_AS_FILENAME);
        $files = array_keys(iterator_to_array($iterator));

        foreach ($files as $fileName) {
            $realPath = sprintf('%s%s%s', $directory, DIRECTORY_SEPARATOR, $fileName);
            $aTime = $this->filesystem->lastAccessed($realPath);
            $elapsed = $time - $aTime;
            if ($elapsed > $delay) {
                $targetPath = str_replace('process', 'message', $realPath);
                $this->filesystem->rename($realPath, $targetPath);
            }
        }
    }

    /**
     * Retrieves the message file name
     *
     * @param MessageInterface $message The message
     *
     * @return string
     */
    private function getMessageFileName(MessageInterface $message): string
    {
        return sprintf('%s.message', $message->id()->toString());
    }

    /**
     * Creates topic directory if needed
     *
     * @param string $topic The topic name
     *
     * @return void
     */
    private function createTopic(string $topic): void
    {
        $directory = $this->getTopicDirectory($topic);

        if ($this->filesystem->isDir($directory)) {
            return;
        }

        $this->filesystem->mkdir($directory);
    }

    /**
     * Retrieves the topic directory
     *
     * @param string $topic The topic name
     *
     * @return string
     */
    private function getTopicDirectory(string $topic): string
    {
        return sprintf(
            '%s%s%s',
            $this->baseDir,
            DIRECTORY_SEPARATOR,
            str_replace(['\\', '.'], '-', $topic)
        );
    }
}
