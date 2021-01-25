<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use GuzzleHttp\Psr7\Utils;
use Novuso\Common\Application\HttpClient\Message\UriFactory;
use Novuso\System\Exception\DomainException;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Class GuzzleUriFactory
 */
final class GuzzleUriFactory implements UriFactory
{
    /**
     * {@inheritdoc}
     */
    public function createUri($uri): UriInterface
    {
        try {
            return Utils::uriFor($uri);
        } catch (Throwable $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
