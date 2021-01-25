<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Auth\Hmac;

use Exception;
use Novuso\Common\Application\Auth\RequestService;
use Psr\Http\Message\RequestInterface;

/**
 * Class HmacRequestService
 */
final class HmacRequestService implements RequestService
{
    use HmacMethods;

    protected string $secret;

    /**
     * Constructs HmacRequestService
     */
    public function __construct(protected string $public, string $private)
    {
        $this->secret = hex2bin($private);
    }

    /**
     * @inheritDoc
     */
    public function signRequest(RequestInterface $request): RequestInterface
    {
        $method = strtoupper($request->getMethod());
        $uri = $this->normalizeUri($request->getUri());
        $request = $request->withUri($uri);

        $authority = $uri->getAuthority();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        $content = (string) $request->getBody();
        $timestamp = time();

        $headers = $this->buildHeaders($timestamp, $content);

        $canonicalRequest = $this->createCanonicalRequestString(
            $method,
            $authority,
            $path,
            $query,
            $headers
        );

        $headers['Authorization'] = 'HMAC-SHA256';
        $headers['Credential'] = $this->public;
        $headers['Signature'] = $this->createSignature(
            $canonicalRequest,
            $timestamp
        );

        ksort($headers);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * Builds standard headers
     *
     * @throws Exception
     */
    protected function buildHeaders(int $timestamp, string $content): array
    {
        $headers = [];

        $headers['X-Timestamp'] = $timestamp;
        $headers['X-Nonce'] = HmacKeyGenerator::generateSecureRandom(8);

        if ($content !== '') {
            $contentHash = hash('sha256', $content);
            $headers['X-Content-SHA256'] = $contentHash;
        }

        return $headers;
    }

    /**
     * @inheritDoc
     */
    protected function getSecret(): string
    {
        return $this->secret;
    }
}
