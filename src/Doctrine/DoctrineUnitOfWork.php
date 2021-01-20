<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Novuso\Common\Application\Repository\UnitOfWork;

/**
 * Class DoctrineUnitOfWork
 */
final class DoctrineUnitOfWork implements UnitOfWork
{
    /**
     * Constructs DoctrineUnitOfWork
     */
    public function __construct(protected EntityManagerInterface $entityManager)
    {
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        return $this->entityManager->transactional($operation);
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return !$this->entityManager->isOpen();
    }
}
