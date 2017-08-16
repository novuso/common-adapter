<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Adapter\EventStore\Doctrine\DbalEventStore;

/**
 * EventStoreCreateSchemaCommand is command that creates a schema for event store
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class EventStoreCreateSchemaCommand extends Command
{
    /**
     * Command name
     *
     * @var string
     */
    protected $name = 'event-store:schema:create';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Creates schema for the event store';

    /**
     * Database event store
     *
     * @var DbalEventStore
     */
    protected $databaseEventStore;

    /**
     * Constructs EventStoreCreateSchemaCommand
     *
     * @param DbalEventStore $databaseEventStore
     */
    public function __construct(DbalEventStore $databaseEventStore)
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
        $this->success('Schema created for DbalEventStore');

        return 0;
    }
}
