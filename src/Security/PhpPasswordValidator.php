<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Security;

use Novuso\Common\Application\Security\PasswordValidator;

/**
 * Class PhpPasswordValidator
 */
final class PhpPasswordValidator implements PasswordValidator
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
    public function validate(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(string $hash): bool
    {
        if ($this->options === null) {
            return password_needs_rehash($hash, $this->algorithm);
        }

        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }
}
