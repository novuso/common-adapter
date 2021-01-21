<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\MessageStore;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Schema\Schema;
use Exception;
use Novuso\Common\Application\Logging\SqlLogger;
use Novuso\Common\Application\Messaging\Exception\MessageStoreException;
use Novuso\Common\Application\Messaging\MessageStore;
use Novuso\Common\Domain\Messaging\Message;
use Novuso\Common\Domain\Messaging\MessageId;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\System\Collection\Iterator\GeneratorIterator;
use Novuso\System\Serialization\Serializer;
use PDO;
use Throwable;

/**
 * Class DbalMessageStore
 */
final class DbalMessageStore implements MessageStore
{
    protected const TIMEZONE = 'UTC';

    /**
     * Constructs DbalMessageStore
     */
    public function __construct(
        protected Connection $connection,
        protected Serializer $serializer,
        protected string $table,
        protected ?SqlLogger $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function add(string $name, Message $message): void
    {
        try {
            $now = DateTime::now(static::TIMEZONE);
            $messageText = $this->serializer->serialize($message);

            $query = $this->connection->createQueryBuilder();
            $query
                ->insert($this->table)
                ->values([
                    'queue'      => ':queue',
                    'message_id' => ':message_id',
                    'message'    => ':message',
                    'created_at' => ':created_at'
                ]);

            $parameters = [
                ':queue'      => [
                    'value' => $name,
                    'type'  => 'string'
                ],
                ':message_id' => [
                    'value' => $message->id()->toString(),
                    'type'  => 'string'
                ],
                ':message'    => [
                    'value' => $messageText,
                    'type'  => 'string'
                ],
                ':created_at' => [
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
            throw new MessageStoreException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $name, MessageId $messageId): ?Message
    {
        try {
            $query = $this->connection->createQueryBuilder();
            $query
                ->select('message')
                ->from($this->table)
                ->where('queue = :queue')
                ->andWhere('message_id = :message_id');

            $parameters = [
                ':queue'      => [
                    'value' => $name,
                    'type'  => 'string'
                ],
                ':message_id' => [
                    'value' => $messageId->toString(),
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

            $record = $statement->fetch();

            if (!$record) {
                return null;
            }

            /** @var Message $message */
            $message = $this->serializer->deserialize($record['message']);

            return $message;
        } catch (Throwable $e) {
            throw new MessageStoreException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getAll(string $name): iterable
    {
        try {
            $query = $this->connection->createQueryBuilder();
            $query
                ->select('message')
                ->from($this->table)
                ->where('queue = :queue');

            $parameters = [
                ':queue' => ['value' => $name, 'type' => 'string']
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            if ($this->logger !== null) {
                $this->logger->log((string) $query, $parameters);
            }

            $statement = $query->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);

            return new GeneratorIterator(function (Statement $statement) {
                foreach ($statement as $record) {
                    /** @var Message $message */
                    $message = $this->serializer->deserialize(
                        $record['message']
                    );

                    yield $message;
                }
            }, [$statement]);
        } catch (Throwable $e) {
            throw new MessageStoreException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(string $name, MessageId $messageId): void
    {
        try {
            $query = $this->connection->createQueryBuilder();
            $query
                ->delete($this->table)
                ->where('queue = :queue')
                ->andWhere('message_id = :message_id');

            $parameters = [
                ':queue'      => [
                    'value' => $name,
                    'type'  => 'string'
                ],
                ':message_id' => [
                    'value' => $messageId->toString(),
                    'type'  => 'string'
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
            throw new MessageStoreException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
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

        $table->addColumn('message_id', 'guid', [
            'length'  => 36,
            'notnull' => true
        ]);

        $table->addColumn('message', 'text', [
            'length'  => 4294967295,
            'notnull' => true
        ]);

        $table->addColumn('created_at', 'datetime', [
            'notnull' => true
        ]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['message_id']);
        $table->addIndex(['queue']);

        return $schema;
    }
}
