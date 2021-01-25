<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use GuzzleHttp\ClientInterface;
use Novuso\Common\Application\HttpClient\Exception\Exception;
use Novuso\Common\Application\HttpClient\Exception\TransferException;
use Novuso\Common\Application\HttpClient\Message\Promise;
use Novuso\Common\Application\HttpClient\Transport\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GuzzleClient
 */
final class GuzzleClient implements HttpClient
{
    /**
     * Constructs GuzzleClient
     */
    public function __construct(protected ClientInterface $client)
    {
    }

    /**
     * @inheritDoc
     */
    public function send(
        RequestInterface $request,
        array $options = []
    ): ResponseInterface {
        $promise = $this->sendAsync($request, $options);
        $promise->wait();

        if ($promise->getState() === Promise::REJECTED) {
            $exception = $promise->getException();

            if (!($exception instanceof Exception)) {
                throw new TransferException(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }

            throw $exception;
        }

        return $promise->getResponse();
    }

    /**
     * @inheritDoc
     */
    public function sendAsync(
        RequestInterface $request,
        array $options = []
    ): Promise {
        $promise = $this->client->sendAsync($request, $options);

        return new GuzzlePromise($promise, $request);
    }
}
