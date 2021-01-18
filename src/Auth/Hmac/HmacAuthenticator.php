<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Auth\Hmac;

use Novuso\Common\Application\Auth\Authenticator;
use Novuso\Common\Application\HttpFoundation\HttpStatus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class HmacAuthenticator
 *
 * The private key should be found by looking up a client by the public key,
 * which is found in the Credential header
 */
final class HmacAuthenticator implements Authenticator
{
    use HmacMethods;

    protected string $secret;
    protected ?int $errorCode = null;
    protected ?string $errorMessage = null;

    protected static array $requiredHeaders = [
        'Authorization',
        'Credential',
        'Signature',
        'X-Timestamp',
        'X-Nonce'
    ];

    /**
     * Constructs HmacAuthenticator
     */
    public function __construct(
        protected string $public,
        string $private,
        protected int $timeTolerance
    ) {
        $this->secret = hex2bin($private);
    }

    /**
     * @inheritDoc
     */
    public function validate(ServerRequestInterface $request): bool
    {
        $server = $request->getServerParams();

        // validate that required headers are present
        foreach (static::$requiredHeaders as $requiredHeader) {
            if (!$request->hasHeader($requiredHeader)) {
                $this->errorCode = HttpStatus::UNPROCESSABLE_ENTITY;
                $this->errorMessage = sprintf(
                    '%s is a required header',
                    $requiredHeader
                );

                return false;
            }
        }

        // validate that the timestamp is in bounds
        $requestTime = $server['REQUEST_TIME'] ?? time();
        $timestamp = (int) $request->getHeaderLine('X-Timestamp');
        $tolerance = $this->timeTolerance;
        if (
            $requestTime < $timestamp
            || $requestTime - $tolerance > $timestamp
        ) {
            $this->errorCode = HttpStatus::BAD_REQUEST;
            $this->errorMessage = 'Timestamp out of bounds';

            return false;
        }

        // validate that the credential matches the public key
        if ($this->public !== $request->getHeaderLine('Credential')) {
            $this->errorCode = HttpStatus::FORBIDDEN;
            $this->errorMessage = 'Not authorized';

            return false;
        }

        // validate that the content matches the content-sha256 hash
        $content = (string) $request->getBody();
        if (!empty($content) && !$request->hasHeader('X-Content-SHA256')) {
            $this->errorCode = HttpStatus::UNPROCESSABLE_ENTITY;
            $this->errorMessage = 'X-Content-SHA256 header is required with content';

            return false;
        }
        if (!empty($content)) {
            $contentHash = hash('sha256', $content);
            if (
                !hash_equals(
                    $contentHash,
                    $request->getHeaderLine('X-Content-SHA256')
                )
            ) {
                $this->errorCode = HttpStatus::BAD_REQUEST;
                $this->errorMessage = 'Invalid content hash';

                return false;
            }
        }

        // validate HMAC request signature
        $method = strtoupper($request->getMethod());
        $uri = $this->normalizeUri($request->getUri());

        $authority = $uri->getAuthority();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        $headers = [];
        $headers['X-Timestamp'] = (int) $request->getHeaderLine('X-Timestamp');
        $headers['X-Nonce'] = $request->getHeaderLine('X-Nonce');
        if ($request->hasHeader('X-Content-SHA256')) {
            $headers['X-Content-SHA256'] = $request->getHeaderLine(
                'X-Content-SHA256'
            );
        }

        $canonicalRequest = $this->createCanonicalRequestString(
            $method,
            $authority,
            $path,
            $query,
            $headers
        );

        $signature = $this->createSignature(
            $canonicalRequest,
            (int) $request->getHeaderLine('X-Timestamp')
        );

        if (!hash_equals($signature, $request->getHeaderLine('Signature'))) {
            $this->errorCode = HttpStatus::FORBIDDEN;
            $this->errorMessage = 'Not authorized';

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @inheritDoc
     */
    protected function getSecret(): string
    {
        return $this->secret;
    }
}
