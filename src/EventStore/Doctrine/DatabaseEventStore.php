<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\EventStore\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Schema\Schema;
use Novuso\Common\Application\EventStore\EventStoreInterface;
use Novuso\Common\Application\EventStore\Exception\ConcurrencyException;
use Novuso\Common\Application\EventStore\Exception\EventStoreException;
use Novuso\Common\Application\EventStore\Exception\StreamNotFoundException;
use Novuso\Common\Application\Logging\SqlLogger;
use Novuso\Common\Domain\EventSourcing\EventRecord;
use Novuso\Common\Domain\Identity\IdentifierInterface;
use Novuso\System\Serialization\SerializerInterface;
use Novuso\System\Type\Type;
use Novuso\System\Utility\Validate;
use Throwable;

/**
 * DatabaseEventStore is a DBAL implementation of an event store
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class DatabaseEventStore implements EventStoreInterface
{
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
     * SQL logger
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
     * Constructs DatabaseEventStore
     *
     * @param Connection          $connection The database connection
     * @param SerializerInterface $serializer The serializer service
     * @param SqlLogger           $logger     The SQL logger
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
    public function append(EventRecord $eventRecord): void
    {
        try {
            $uuid = $eventRecord->aggregateId()->toString();
            $type = $eventRecord->aggregateType()->toString();
            $event = $this->serializer->serialize($eventRecord);
            $version = $eventRecord->sequenceNumber();

            $query = $this->connection->createQueryBuilder();
            $query
                ->insert($this->table)
                ->values([
                    'uuid'    => ':uuid',
                    'type'    => ':type',
                    'event'   => ':event',
                    'version' => ':version'
                ]);

            $parameters = [
                ':uuid'    => ['value' => $uuid, 'type' => 'string'],
                ':type'    => ['value' => $type, 'type' => 'string'],
                ':event'   => ['value' => $event, 'type' => 'string'],
                ':version' => ['value' => $version, 'type' => 'integer']
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            $this->logger->log((string) $query, $parameters);

            $query->execute();
        } catch (ConcurrencyException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new EventStoreException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function appendStream($eventStream): void
    {
        assert(Validate::isTraversable($eventStream), 'Event stream is not traversable');

        foreach ($eventStream as $eventRecord) {
            $this->append($eventRecord);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readStream(Type $type, IdentifierInterface $id, ?int $first = null, ?int $last = null)
    {
        $typeString = $type->toString();
        $idString = $id->toString();

        if (!$this->hasStream($type, $id)) {
            $message = sprintf('Stream not found for [%s]{%s}', $typeString, $idString);
            throw new StreamNotFoundException($message);
        }

        try {
            $statement = $this->getReadStreamStatement($typeString, $idString, $first, $last);

            foreach ($statement as $record) {
                yield $this->serializer->deserialize($record['event']);
            }
        } catch (Throwable $e) {
            throw new EventStoreException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasStream(Type $type, IdentifierInterface $id): bool
    {
        try {
            $query = $this->connection->createQueryBuilder();
            $query
                ->select('id')
                ->from($this->table)
                ->where('type = :type')
                ->andWhere('uuid = :uuid');

            $parameters = [
                ':type' => ['value' => $type->toString(), 'type' => 'string'],
                ':uuid' => ['value' => $id->toString(), 'type' => 'string']
            ];

            foreach ($parameters as $key => $data) {
                $query->setParameter($key, $data['value'], $data['type']);
            }

            $this->logger->log((string) $query, $parameters);

            $statement = $query->execute();

            if ($statement->fetch()) {
                return true;
            }

            return false;
        } catch (Throwable $e) {
            throw new EventStoreException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Creates event store table if needed
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
     * Retrieves the read stream statement
     *
     * @param string   $typeString The aggregate type
     * @param string   $idString   The aggregate ID
     * @param int|null $first      The first version
     * @param int|null $last       The last version
     *
     * @return Statement
     */
    protected function getReadStreamStatement(
        string $typeString,
        string $idString,
        ?int $first = null,
        ?int $last = null
    ): Statement {
        $query = $this->connection->createQueryBuilder();
        $query
            ->select(
                'uuid',
                'type',
                'event',
                'version'
            )
            ->from($this->table)
            ->where('type = :type')
            ->andWhere('uuid = :uuid');

        $parameters = [
            ':type' => ['value' => $typeString, 'type' => 'string'],
            ':uuid' => ['value' => $idString, 'type' => 'string']
        ];

        if ($first !== null) {
            $query->andWhere('version >= :first');
            $parameters[':first'] = ['value' => $first, 'type' => 'integer'];
        }

        if ($last !== null) {
            $query->andWhere('version <= :last');
            $parameters[':last'] = ['value' => $last, 'type' => 'integer'];
        }

        foreach ($parameters as $key => $data) {
            $query->setParameter($key, $data['value'], $data['type']);
        }

        $this->logger->log((string) $query, $parameters);

        return $query->execute();
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

        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'unsigned'      => true,
            'notnull'       => true
        ]);
        $table->addColumn('uuid', 'guid', [
            'length'  => 36,
            'notnull' => true
        ]);
        $table->addColumn('type', 'string', [
            'length'  => 255,
            'notnull' => false
        ]);
        $table->addColumn('event', 'text', [
            'notnull' => true
        ]);
        $table->addColumn('version', 'integer', [
            'unsigned' => true,
            'notnull'  => true
        ]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['type', 'uuid', 'version']);

        return $schema;
    }
}
