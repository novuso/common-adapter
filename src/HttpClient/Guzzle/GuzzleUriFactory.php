<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use Novuso\Common\Application\HttpClient\Message\UriFactoryInterface;
use Novuso\System\Exception\DomainException;
use Psr\Http\Message\UriInterface;
use Throwable;
use function GuzzleHttp\Psr7\uri_for;

/**
 * GuzzleUriFactory is a Guzzle URI factory
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class GuzzleUriFactory implements UriFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createUri($uri): UriInterface
    {
        try {
            return uri_for($uri);
        } catch (Throwable $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
