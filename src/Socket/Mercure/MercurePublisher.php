<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Socket\Mercure;

use Novuso\Common\Application\Socket\Exception\SocketException;
use Novuso\Common\Application\Socket\Publisher;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Class MercurePublisher
 */
final class MercurePublisher implements Publisher
{
    /**
     * Constructs MercurePublisher
     */
    public function __construct(protected PublisherInterface $mercure)
    {
    }

    /**
     * @inheritDoc
     */
    public function push(string $topic, string $message): void
    {
        try {
            $update = new Update($topic, $message);

            $this->mercure->__invoke($update);
        } catch (Throwable $e) {
            throw new SocketException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
