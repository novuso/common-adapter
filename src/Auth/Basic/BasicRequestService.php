<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Auth\Basic;

use Novuso\Common\Application\Auth\RequestService;
use Novuso\System\Exception\DomainException;
use Psr\Http\Message\RequestInterface;

/**
 * Class BasicRequestService
 */
final class BasicRequestService implements RequestService
{
    /**
     * Constructs BasicRequestService
     *
     * @throws DomainException When the username is invalid
     */
    public function __construct(
        protected string $username,
        protected string $password
    ) {
        if (str_contains($this->username, ':')) {
            throw new DomainException('Username may not contain a colon');
        }
    }

    /**
     * @inheritDoc
     */
    public function signRequest(RequestInterface $request): RequestInterface
    {
        $credentials = sprintf('%s:%s', $this->username, $this->password);
        $authorization = sprintf('Basic %s', base64_encode($credentials));

        return $request->withHeader('Authorization', $authorization);
    }
}
