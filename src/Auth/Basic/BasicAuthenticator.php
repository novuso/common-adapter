<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Auth\Basic;

use Novuso\Common\Application\Auth\Authenticator;
use Novuso\Common\Application\HttpFoundation\HttpStatus;
use Novuso\Common\Application\Security\PasswordValidator;
use Novuso\System\Exception\DomainException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class BasicAuthenticator
 */
final class BasicAuthenticator implements Authenticator
{
    protected ?int $errorCode = null;
    protected ?string $errorMessage = null;

    /**
     * Constructs BasicAuthenticator
     *
     * @throws DomainException When the username is invalid
     */
    public function __construct(
        protected PasswordValidator $passwordValidator,
        protected string $username,
        protected string $passwordHash
    ) {
        if (str_contains($this->username, ':')) {
            throw new DomainException('Username may not contain a colon');
        }
    }

    /**
     * @inheritDoc
     */
    public function validate(ServerRequestInterface $request): bool
    {
        if (!$request->hasHeader('Authorization')) {
            $this->errorCode = HttpStatus::UNAUTHORIZED;
            $this->errorMessage = 'Unauthorized';

            return false;
        }

        $authValues = $request->getHeader('Authorization');
        $authorization = reset($authValues);
        [$type, $credentials] = explode(' ', $authorization);

        if (strtolower($type) !== 'basic') {
            $this->errorCode = HttpStatus::FORBIDDEN;
            $this->errorMessage = 'Not authorized';

            return false;
        }

        $credentials = base64_decode($credentials);

        if (!str_contains($credentials, ':')) {
            $this->errorCode = HttpStatus::FORBIDDEN;
            $this->errorMessage = 'Not authorized';

            return false;
        }

        [$username, $password] = explode(':', $credentials);

        if ($this->username !== $username) {
            $this->errorCode = HttpStatus::FORBIDDEN;
            $this->errorMessage = 'Not authorized';

            return false;
        }

        if (!$this->passwordValidator->validate($password, $this->passwordHash)) {
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
}
