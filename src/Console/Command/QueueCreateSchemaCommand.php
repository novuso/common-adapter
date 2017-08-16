<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Command;

use Novuso\Common\Adapter\Console\Command;
use Novuso\Common\Adapter\Messaging\MySqlMessageQueue;

/**
 * QueueCreateSchemaCommand is command that creates a schema for message queue
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class QueueCreateSchemaCommand extends Command
{
    /**
     * Command name
     *
     * @var string
     */
    protected $name = 'queue:schema:create';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Creates MySQL schema for the message queue';

    /**
     * MySQL message queue
     *
     * @var MySqlMessageQueue
     */
    protected $mySqlMessageQueue;

    /**
     * Constructs QueueCreateSchemaCommand
     *
     * @param MySqlMessageQueue $mySqlMessageQueue
     */
    public function __construct(MySqlMessageQueue $mySqlMessageQueue)
    {
        $this->mySqlMessageQueue = $mySqlMessageQueue;
        parent::__construct();
    }

    /**
     * Fires the command
     *
     * @return int
     */
    protected function fire(): int
    {
        $this->mySqlMessageQueue->createSchema();
        $this->success('Schema created for MySqlMessageQueue');

        return 0;
    }
}
