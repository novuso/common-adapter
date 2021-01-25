<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Guzzle;

use GuzzleHttp\Exception as GuzzleExceptions;
use GuzzleHttp\Promise\PromiseInterface;
use Novuso\Common\Application\HttpClient\Exception as NovusoExceptions;
use Novuso\Common\Application\HttpClient\Message\Promise;
use Novuso\System\Exception\MethodCallException;
use Novuso\System\Exception\RuntimeException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class GuzzlePromise
 */
final class GuzzlePromise implements Promise
{
    protected PromiseInterface $promise;
    protected string $state;
    protected RequestInterface $request;
    protected ?ResponseInterface $response;
    protected ?Throwable $exception;

    /**
     * Constructs GuzzlePromise
     */
    public function __construct(
        PromiseInterface $promise,
        RequestInterface $request
    ) {
        $this->request = $request;
        $this->state = Promise::PENDING;
        $this->promise = $promise->then(
            function (ResponseInterface $response) {
                $this->response = $response;
                $this->state = Promise::FULFILLED;

                return $response;
            },
            function (Throwable $reason) use ($request) {
                if ($reason instanceof NovusoExceptions\Exception) {
                    $this->state = Promise::REJECTED;
                    $this->exception = $reason;

                    throw $this->exception;
                }

                if (!($reason instanceof GuzzleExceptions\GuzzleException)) {
                    $this->state = Promise::REJECTED;
                    $this->exception = new RuntimeException('Invalid reason');

                    throw $this->exception;
                }

                $this->state = Promise::REJECTED;
                $this->exception = $this->handleException($reason);

                throw $this->exception;
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): static {
        return new static(
            $this->promise->then($onFulfilled, $onRejected),
            $this->request
        );
    }

    /**
     * @inheritDoc
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): ResponseInterface
    {
        if ($this->state !== Promise::FULFILLED) {
            throw new MethodCallException(
                'Response not available for the current state'
            );
        }

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function getException(): Throwable
    {
        if ($this->state !== Promise::REJECTED) {
            throw new MethodCallException(
                'Error not available for the current state'
            );
        }

        return $this->exception;
    }

    /**
     * @inheritDoc
     */
    public function wait(): void
    {
        $this->promise->wait(false);
    }

    /**
     * Converts a Guzzle exception into a Novuso exception
     */
    protected function handleException(Throwable $exception): Throwable
    {
        if ($exception instanceof GuzzleExceptions\ConnectException) {
            return new NovusoExceptions\NetworkException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception
            );
        }

        if ($exception instanceof GuzzleExceptions\RequestException) {
            if ($exception->hasResponse()) {
                return new NovusoExceptions\HttpException(
                    $exception->getMessage(),
                    $exception->getRequest(),
                    $exception->getResponse(),
                    $exception
                );
            }

            return new NovusoExceptions\RequestException(
                $exception->getMessage(),
                $exception->getRequest(),
                $exception
            );
        }

        return new NovusoExceptions\TransferException(
            $exception->getMessage(),
            0,
            $exception
        );
    }
}
