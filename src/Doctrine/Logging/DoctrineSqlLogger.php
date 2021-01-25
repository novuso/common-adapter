<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine\Logging;

use Doctrine\DBAL\Logging\SQLLogger;
use Novuso\Common\Application\Logging\SqlLogger as Logger;

/**
 * Class DoctrineSqlLogger
 */
final class DoctrineSqlLogger implements SQLLogger
{
    public function __construct(protected Logger $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public function startQuery(
        $sql,
        ?array $params = null,
        ?array $types = null
    ) {
        $this->logger->log($sql, ['params' => $params, 'types' => $types]);
    }

    /**
     * @inheritDoc
     */
    public function stopQuery()
    {
    }
}
