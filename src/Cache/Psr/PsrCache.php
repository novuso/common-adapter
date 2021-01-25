<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Cache\Psr;

use Novuso\Common\Application\Cache\Cache;
use Novuso\Common\Application\Cache\Exception\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class PsrCache
 */
final class PsrCache implements Cache
{
    /**
     * Constructs PsrCache
     */
    public function __construct(
        protected CacheItemPoolInterface $cachePool,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(string $key, callable $loader, int $ttl): mixed
    {
        try {
            $cacheItem = $this->cachePool->getItem($key);

            if (!$cacheItem->isHit()) {
                $this->logger->debug(sprintf('Cache MISS: "%s"', $key));

                $results = $loader();

                $cacheItem->set($results);
                $cacheItem->expiresAfter($ttl);

                $this->cachePool->save($cacheItem);
            } else {
                $this->logger->debug(sprintf('Cache HIT: "%s"', $key));
            }

            return $cacheItem->get();
        } catch (Throwable $e) {
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
