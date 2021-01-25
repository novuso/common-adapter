<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use GuzzleHttp\Psr7\Utils;
use Novuso\Common\Application\HttpClient\Message\StreamFactory;
use Novuso\System\Exception\DomainException;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Class GuzzleStreamFactory
 */
final class GuzzleStreamFactory implements StreamFactory
{
    /**
     * @inheritDoc
     */
    public function createStream(mixed $body = null): StreamInterface
    {
        try {
            return Utils::streamFor($body);
        } catch (Throwable $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
