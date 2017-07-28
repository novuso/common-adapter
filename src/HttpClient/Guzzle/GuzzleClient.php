<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use GuzzleHttp\ClientInterface;
use Novuso\Common\Application\HttpClient\HttpClientInterface;
use Novuso\Common\Application\HttpClient\Message\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * GuzzleClient is a Guzzle HTTP client adapter
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class GuzzleClient implements HttpClientInterface
{
    /**
     * Guzzle client
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Constructs GuzzleClient
     *
     * @param ClientInterface $client The Guzzle client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $promise = $this->sendAsync($request, $options);
        $promise->wait();

        if ($promise->getState() === PromiseInterface::REJECTED) {
            throw $promise->getException();
        }

        return $promise->getResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        $promise = $this->client->sendAsync($request, $options);

        return new GuzzlePromise($promise, $request);
    }
}
