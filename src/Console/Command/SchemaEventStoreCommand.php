<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Adapter\EventStore\Doctrine\DatabaseEventStore;

/**
 * SchemaEventStoreCommand is command that creates a schema for event store
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class SchemaEventStoreCommand extends Command
{
    /**
     * Command name
     *
     * @var string
     */
    protected $name = 'schema:event-store';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Creates schema for the event store';

    /**
     * Database event store
     *
     * @var DatabaseEventStore
     */
    protected $databaseEventStore;

    /**
     * SchemaMessageQueueCommand constructor.
     *
     * @param DatabaseEventStore $databaseEventStore
     */
    public function __construct(DatabaseEventStore $databaseEventStore)
    {
        $this->databaseEventStore = $databaseEventStore;
        parent::__construct();
    }

    /**
     * Fires the command
     *
     * @return int
     */
    protected function fire(): int
    {
        $this->databaseEventStore->createSchema();
        $this->success('Schema created for DatabaseEventStore');

        return 0;
    }
}
