<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\MessageQueue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Exception;
use Novuso\Common\Application\Logging\SqlLogger;
use Novuso\Common\Application\Messaging\Exception\MessageQueueException;
use Novuso\Common\Application\Messaging\MessageQueue;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\Common\Domain\Messaging\MetaData;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\Common\Domain\Value\Identifier\Uuid;
use Novuso\System\Exception\DomainException;
use Novuso\System\Serialization\Serializer;
use PDO;
use Throwable;

/**
 * Class MySqlMessageQueue
 */
final class MySqlMessageQueue implements MessageQueue
{
    protected const META_KEY = 'db_handle';
    protected const STATUS_QUEUED = 'queued';
    protected const STATUS_DISPATCHED = 'dispatched';
    protected const STATUS_ACKNOWLEDGED = 'acknowledged';
    protected const STATUS_REJECTED = 'rejected';
    protected const TIMEZONE = 'UTC';

    /**
     * Constructs MySqlMessageQueue
     */
    public function __construct(
        protected Connection $connection,
        protected Serializer $serializer,
        protected string $table,
        protected ?SqlLogger $logger = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function enqueue(string $name, Message $message): void
    {
        try {
            $now = DateTime::now(static::TIMEZONE);
            $messageText = $this->serializer->serialize($message);

            $query = $this->connection->createQueryBuilder();
            $query
                ->insert($this->table)
                ->values([
                    'queue'      => ':queue',
                    'message'    => ':message',
                    'status'     => ':status',
                    'created_at' => ':created_at',
                    'updated_at' => ':updated_at'
                ]);

            $parameters = [
                ':queue'      => [
                    'value' => $name,
                    'type'  => 'string'
                ],
                ':message'    => [
                    'value' => $messageText,
                    'type'  => 'string'
                ],
                ':status'     => [
                    'value' => static::STATUS_QUEUED,
                    'type'  => 'string'
                ],
                ':created_at' => [
                    'value' => $now->toNative(),
                    'type'  => 'datetime'
                ],
                ':updated_at' => [
                    'value' => $now->toNative(),
                    'type'  => 'datetime'
                ]
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            if ($this->logger !== null) {
                $this->logger->log((string) $query, $parameters);
            }

            $query->execute();
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
            $now = DateTime::now(static::TIMEZONE);
            $handle = Uuid::comb()->toString();

            // update with limit is not portable
            // using MySQL syntax
            $update = [];
            $update[] = 'UPDATE %s';
            $update[] = 'SET handle = :handle,';
            $update[] = 'status = :status,';
            $update[] = 'updated_at = :updated_at';
            $update[] = 'WHERE queue = :queue';
            $update[] = 'AND handle IS NULL';
            $update[] = 'LIMIT 1';
            $sql = sprintf(implode(' ', $update), $this->table);

            $statement = $this->connection->prepare($sql);

            $parameters = [
                'queue'      => [
                    'value' => $name,
                    'type'  => 'string'
                ],
                'handle'     => [
                    'value' => $handle,
                    'type'  => 'string'
                ],
                'status'     => [
                    'value' => static::STATUS_DISPATCHED,
                    'type'  => 'string'
                ],
                'updated_at' => [
                    'value' => $now->toNative(),
                    'type'  => 'datetime'
                ]
            ];

            foreach ($parameters as $key => $data) {
                $statement->bindValue($key, $data['value'], $data['type']);
            }

            $statement->execute();

            if ($statement->rowCount() === 0) {
                return null;
            }

            // only log update SQL when a message is available
            if ($this->logger !== null) {
                $this->logger->log($sql, $parameters);
            }

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

            if ($this->logger !== null) {
                $this->logger->log((string) $query, $parameters);
            }

            $statement = $query->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);

            $record = $statement->fetch();

            /** @var Message $message */
            $message = $this->serializer->deserialize($record['message']);
            $message->metaData()->set(static::META_KEY, $handle);

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
            $now = DateTime::now(static::TIMEZONE);

            if (!$message->metaData()->has(static::META_KEY)) {
                $message = sprintf(
                    'Message meta data missing %s',
                    static::META_KEY
                );
                throw new DomainException($message);
            }

            $handle = $message->metaData()->get(static::META_KEY);

            $query = $this->connection->createQueryBuilder();
            $query
                ->update($this->table)
                ->set('status', ':status')
                ->set('updated_at', ':updated_at')
                ->where('handle = :handle');

            $parameters = [
                ':handle'     => [
                    'value' => $handle,
                    'type'  => 'string'
                ],
                ':status'     => [
                    'value' => static::STATUS_ACKNOWLEDGED,
                    'type'  => 'string'
                ],
                ':updated_at' => [
                    'value' => $now->toNative(),
                    'type'  => 'datetime'
                ]
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            if ($this->logger !== null) {
                $this->logger->log((string) $query, $parameters);
            }

            $query->execute();
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
            $now = DateTime::now(static::TIMEZONE);

            if (!$message->metaData()->has(static::META_KEY)) {
                $message = sprintf(
                    'Message meta data missing %s',
                    static::META_KEY
                );
                throw new DomainException($message);
            }

            $handle = $message->metaData()->get(static::META_KEY);

            $query = $this->connection->createQueryBuilder();
            $query
                ->update($this->table)
                ->set('status', ':status')
                ->set('updated_at', ':updated_at')
                ->where('handle = :handle');

            $parameters = [
                ':handle'     => [
                    'value' => $handle,
                    'type'  => 'string'
                ],
                ':status'     => [
                    'value' => static::STATUS_REJECTED,
                    'type'  => 'string'
                ],
                ':updated_at' => [
                    'value' => $now->toNative(),
                    'type'  => 'datetime'
                ]
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            if ($this->logger !== null) {
                $this->logger->log((string) $query, $parameters);
            }

            $query->execute();

            if ($requeue) {
                $this->enqueue(
                    $name,
                    $message->withMetaData(MetaData::create())
                );
            }
        } catch (Throwable $e) {
            throw new MessageQueueException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Recycles dequeued messages that have not been acknowledged
     *
     * When using the MySqlMessageQueue, you should have a separate script
     * call this method regularly. For instance, you might want a cron job to
     * run every few minutes depending on your needs.
     *
     * @throws Exception When an error occurs
     */
    public function recycleMessages(string $name, int $delay = 600): void
    {
        $timestamp = time();

        $query = $this->connection->createQueryBuilder();
        $query
            ->select('id', 'message', 'updated_at')
            ->from($this->table)
            ->where('queue = :queue')
            ->andWhere('status = :status');

        $parameters = [
            ':queue'  => [
                'value' => $name,
                'type'  => 'string'
            ],
            ':status' => [
                'value' => static::STATUS_DISPATCHED,
                'type'  => 'string'
            ]
        ];

        foreach ($parameters as $key => $data) {
            $query->setParameter($key, $data['value'], $data['type']);
        }

        if ($this->logger !== null) {
            $this->logger->log((string) $query, $parameters);
        }

        $statement = $query->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        $marked = [];
        $messages = [];
        foreach ($statement as $record) {
            $lastUpdated = strtotime($record['updated_at']);
            $elapsed = $timestamp - $lastUpdated;
            if ($elapsed > $delay) {
                /** @var Message $message */
                $message = $this->serializer->deserialize($record['message']);
                $messages[] = $message;
                $marked[] = $record['id'];
            }
        }

        if (!empty($marked)) {
            $query = $this->connection->createQueryBuilder();
            $query
                ->update($this->table)
                ->set('status', ':status')
                ->set('updated_at', ':updated_at')
                ->where(sprintf('id IN (%s)', implode(',', $marked)));

            $parameters = [
                ':status'     => [
                    'value' => static::STATUS_REJECTED,
                    'type'  => 'string'
                ],
                ':updated_at' => [
                    'value' => DateTime::fromTimestamp($timestamp)->toNative(),
                    'type'  => 'datetime'
                ]
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            if ($this->logger !== null) {
                $this->logger->log((string) $query, $parameters);
            }

            $query->execute();
        }

        if (!empty($messages)) {
            foreach ($messages as $message) {
                $this->enqueue(
                    $name,
                    $message->withMetaData(MetaData::create())
                );
            }
        }
    }

    /**
     * Creates queue table if needed
     *
     * @throws Exception When an error occurs
     */
    public function createSchema(): void
    {
        if ($this->tableExists()) {
            return;
        }

        $schema = $this->getSchema();
        $queries = $schema->toSql($this->connection->getDatabasePlatform());

        foreach ($queries as $query) {
            if ($this->logger !== null) {
                $this->logger->log($query);
            }
            $this->connection->exec($query);
        }
    }

    /**
     * Checks if table exists
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
     */
    protected function getSchema(): Schema
    {
        $schemaManager = $this->connection->getSchemaManager();
        $schema = $schemaManager->createSchema();

        $table = $schema->createTable($this->table);

        $table->addColumn('id', 'bigint', [
            'autoincrement' => true,
            'unsigned'      => true,
            'notnull'       => true
        ]);

        $table->addColumn('queue', 'string', [
            'length'  => 255,
            'notnull' => true
        ]);

        $table->addColumn('handle', 'guid', [
            'length'  => 36,
            'notnull' => false
        ]);

        $table->addColumn('message', 'text', [
            'length'  => 4294967295,
            'notnull' => true
        ]);

        $table->addColumn('status', 'string', [
            'length'  => 12,
            'notnull' => true
        ]);

        $table->addColumn('created_at', 'datetime', [
            'notnull' => true
        ]);

        $table->addColumn('updated_at', 'datetime', [
            'notnull' => true
        ]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue']);
        $table->addIndex(['handle']);

        return $schema;
    }
}
