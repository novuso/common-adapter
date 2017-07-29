<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpClient\Logging;

use Novuso\Common\Application\HttpClient\HttpClientInterface;
use Novuso\Common\Application\HttpClient\Message\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * LoggingHttpClient is an HTTP client logger adapter
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class LoggingHttpClient implements HttpClientInterface
{
    /**
     * HTTP client
     *
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Log level
     *
     * @var string
     */
    protected $logLevel;

    /**
     * Constructs LoggingHttpClient
     *
     * @param HttpClientInterface $httpClient The HTTP client
     * @param LoggerInterface     $logger     The logger service
     * @param string              $logLevel   The log level
     */
    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, $logLevel = LogLevel::DEBUG)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->logLevel = $logLevel;
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
        $this->logger->log(
            $this->logLevel,
            '[HTTP]: Outgoing HTTP Request',
            [
                'method'   => $request->getMethod(),
                'uri'      => (string) $request->getUri(),
                'protocol' => $request->getProtocolVersion(),
                'headers'  => $request->getHeaders(),
                'content'  => (string) $request->getBody()
            ]
        );

        $promise = $this->httpClient->sendAsync($request, $options);

        return $promise->then(
            function (ResponseInterface $response) {
                $stream = $response->getBody();

                $this->logger->log(
                    $this->logLevel,
                    '[HTTP]: Incoming HTTP Response',
                    [
                        'status'   => $response->getStatusCode(),
                        'reason'   => $response->getReasonPhrase(),
                        'protocol' => $response->getProtocolVersion(),
                        'headers'  => $response->getHeaders(),
                        'content'  => $stream->getContents()
                    ]
                );

                $stream->rewind();

                return $response;
            },
            function (Throwable $exception) {
                $this->logger->log(
                    $this->logLevel,
                    '[HTTP]: HTTP Error Exception',
                    [
                        'message'   => $exception->getMessage(),
                        'exception' => $exception
                    ]
                );

                throw $exception;
            }
        );
    }
}
