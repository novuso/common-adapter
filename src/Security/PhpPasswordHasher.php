<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Security;

use Novuso\Common\Application\Security\Exception\PasswordException;
use Novuso\Common\Application\Security\PasswordHasher;

/**
 * Class PhpPasswordHasher
 */
final class PhpPasswordHasher implements PasswordHasher
{
    /**
     * Constructs PhpPasswordHasher
     */
    public function __construct(
        protected string $algorithm,
        protected ?array $options = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function hash(string $password): string
    {
        // Null bytes truncate the hash, so don't try to hash them.
        // see: https://blog.ircmaxell.com/2015/03/security-issue-combining-bcrypt-with.html
        if (str_contains($password, chr(0))) {
            throw new PasswordException('Unexpected value received');
        }

        if ($this->options === null) {
            return password_hash($password, $this->algorithm);
        }

        return password_hash($password, $this->algorithm, $this->options);
    }
}
