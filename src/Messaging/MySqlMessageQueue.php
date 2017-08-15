<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Novuso\Common\Application\Logging\SqlLogger;
use Novuso\Common\Application\Messaging\MessageQueueInterface;
use Novuso\Common\Domain\Messaging\MessageInterface;
use Novuso\Common\Domain\Value\Identifier\Uuid;
use Novuso\System\Serialization\SerializerInterface;
use PDO;

/**
 * MySqlMessageQueue is a Doctrine DBAL message queue for MySQL
 *
 * @link      https://github.com/php-amqplib/php-amqplib AMQP Lib
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class MySqlMessageQueue implements MessageQueueInterface
{
    /**
     * Meta data sequence key
     *
     * @var string
     */
    protected const META_KEY = 'db_handle';

    /**
     * Database connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Serializer
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * The SQL logger
     *
     * @var SqlLogger
     */
    protected $logger;

    /**
     * Table name
     *
     * @var string
     */
    protected $table;

    /**
     * Constructs DbalMessageQueue
     *
     * @param Connection          $connection The Doctrine DBAL connection
     * @param SerializerInterface $serializer The serializer service
     * @param SqlLogger           $logger     The SQL logger service
     * @param string              $table      The table name
     */
    public function __construct(
        Connection $connection,
        SerializerInterface $serializer,
        SqlLogger $logger,
        string $table
    ) {
        $this->connection = $connection;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(string $topic, MessageInterface $message): void
    {
        $timestamp = time();
        $messageText = $this->serializer->serialize($message);

        $query = $this->connection->createQueryBuilder();
        $query
            ->insert($this->table)
            ->values([
                'topic'     => ':topic',
                'message'   => ':message',
                'timestamp' => ':timestamp'
            ]);

        $parameters = [
            ':topic'     => ['value' => $topic, 'type' => 'string'],
            ':message'   => ['value' => $messageText, 'type' => 'string'],
            ':timestamp' => ['value' => $timestamp, 'type' => 'integer']
        ];

        foreach ($parameters as $key => $data) {
            $query->setParameter($key, $data['value'], $data['type']);
        }

        $this->logger->log((string) $query, $parameters);

        $query->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(string $topic): ?MessageInterface
    {
        $timestamp = time();
        $handle = Uuid::comb()->hashValue();

        // update with limit is not portable;
        // using MySQL syntax instead of builder
        $update = [];
        $update[] = 'UPDATE %s';
        $update[] = 'SET handle = :handle,';
        $update[] = 'timestamp = :timestamp';
        $update[] = 'WHERE topic = :topic';
        $update[] = 'AND handle IS NULL';
        $update[] = 'LIMIT 1';
        $sql = implode(' ', $update);

        $statement = $this->connection->prepare($sql);

        $parameters = [
            'handle'    => ['value' => $handle, 'type' => 'string'],
            'timestamp' => ['value' => $timestamp, 'type' => 'integer'],
            'topic'     => ['value' => $topic, 'type' => 'string']
        ];

        foreach ($parameters as $key => $data) {
            $statement->bindValue($key, $data['value'], $data['type']);
        }

        $statement->execute();

        if ($statement->rowCount() === 0) {
            return null;
        }

        // only log update SQL when a message is available
        $this->logger->log($sql, $parameters);

        $query = $this->connection->createQueryBuilder();
        $query
            ->select('message')
            ->from($this->table)
            ->where('handle = :handle');

        $parameters = [
            ':handle' => ['value' => $handle, 'type' => 'string']
        ];

        foreach ($parameters as $key => $data) {
            $query->setParameter($key, $data['value'], $data['type']);
        }

        $this->logger->log((string) $query, $parameters);

        $statement = $query->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        $record = $statement->fetch();

        /** @var MessageInterface $message */
        $message = $this->serializer->deserialize($record['message']);
        $message->metaData()->set(static::META_KEY, $handle);

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(string $topic, MessageInterface $message): void
    {
        $handle = $message->metaData()->get(static::META_KEY);

        $query = $this->connection->createQueryBuilder();
        $query
            ->delete($this->table)
            ->where('handle = :handle')
            ->setMaxResults(1);

        $parameters = [
            ':handle' => ['value' => $handle, 'type' => 'string']
        ];

        foreach ($parameters as $key => $data) {
            $query->setParameter($key, $data['value'], $data['type']);
        }

        $this->logger->log((string) $query, $parameters);

        $query->execute();
    }

    /**
     * Recycles dequeued messages that have not been acknowledged
     *
     * When using the MySqlMessageQueue, you should have a separate script call
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
        $timestamp = time();

        $query = $this->connection->createQueryBuilder();
        $query
            ->select('id', 'message', 'timestamp')
            ->from($this->table)
            ->where('topic = :topic')
            ->andWhere('handle IS NOT NULL');

        $parameters = [
            ':topic' => ['value' => $topic, 'type' => 'string']
        ];

        foreach ($parameters as $key => $data) {
            $query->setParameter($key, $data['value'], $data['type']);
        }

        $this->logger->log((string) $query, $parameters);

        $statement = $query->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        $marked = [];
        foreach ($statement as $record) {
            $lastUpdated = (int) $record['timestamp'];
            $elapsed = $timestamp - $lastUpdated;
            if ($elapsed > $delay) {
                /** @var MessageInterface $message */
                $message = $this->serializer->deserialize($record['message']);
                $this->enqueue($topic, $message);
                $marked[] = $record['id'];
            }
        }

        if (!empty($marked)) {
            $query = $this->connection->createQueryBuilder();
            $query
                ->delete($this->table)
                ->where(sprintf('id IN (%s)', implode(',', $marked)));

            $this->logger->log((string) $query);

            $query->execute();
        }
    }

    /**
     * Creates queue table if needed
     *
     * @return void
     */
    public function createSchema(): void
    {
        if ($this->tableExists()) {
            return;
        }

        $schema = $this->getCreateSchema();
        $queries = $schema->toSql($this->connection->getDatabasePlatform());

        foreach ($queries as $query) {
            $this->logger->log($query);
            $this->connection->exec($query);
        }
    }

    /**
     * Checks if table exists
     *
     * @return bool
     */
    protected function tableExists(): bool
    {
        $schemaManager = $this->connection->getSchemaManager();

        if ($schemaManager->tablesExist([$this->table])) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves the schema to create message table
     *
     * @return Schema
     */
    protected function getCreateSchema(): Schema
    {
        $schemaManager = $this->connection->getSchemaManager();
        $schema = $schemaManager->createSchema();

        $table = $schema->createTable($this->table);

        $table->addColumn('id', 'bigint', [
            'autoincrement' => true,
            'unsigned'      => true,
            'notnull'       => true
        ]);
        $table->addColumn('topic', 'string', [
            'length'  => 255,
            'notnull' => true
        ]);
        $table->addColumn('handle', 'string', [
            'length'  => 32,
            'notnull' => false
        ]);
        $table->addColumn('message', 'text', [
            'notnull' => true
        ]);
        $table->addColumn('timestamp', 'integer', [
            'unsigned' => true,
            'notnull'  => true
        ]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['topic']);
        $table->addIndex(['handle']);

        return $schema;
    }
}
