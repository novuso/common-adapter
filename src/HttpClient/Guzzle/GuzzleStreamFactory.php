<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use Novuso\Common\Application\HttpClient\Message\StreamFactoryInterface;
use Novuso\System\Exception\DomainException;
use Psr\Http\Message\StreamInterface;
use Throwable;
use function GuzzleHttp\Psr7\stream_for;

/**
 * GuzzleStreamFactory is a Guzzle stream factory
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class GuzzleStreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createStream($body = null): StreamInterface
    {
        try {
            return stream_for($body);
        } catch (Throwable $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
