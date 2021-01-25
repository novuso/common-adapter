<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Security;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Novuso\Common\Application\Security\Exception\TokenException;
use Novuso\Common\Application\Security\TokenDecoder;
use Novuso\System\Exception\DomainException;
use Throwable;

/**
 * Class JwtDecoder
 */
final class JwtDecoder implements TokenDecoder
{
    /**
     * Supported algorithms
     */
    protected static array $algorithms = [
        'HS256' => Sha256::class,
        'HS384' => Sha384::class,
        'HS512' => Sha512::class
    ];

    protected Configuration $configuration;

    /**
     * Constructs JwtDecoder
     *
     * @throws DomainException When algorithm is not supported
     */
    public function __construct(string $hexSecret, string $algorithm = 'HS256')
    {
        $key = InMemory::plainText(hex2bin($hexSecret));

        if (!isset(static::$algorithms[$algorithm])) {
            $message = sprintf('Unsupported algorithm: %s', $algorithm);
            throw new DomainException($message);
        }

        $algorithmClass = static::$algorithms[$algorithm];
        $this->configuration = Configuration::forSymmetricSigner(
            new $algorithmClass(),
            $key
        );
        $this->configuration->setValidationConstraints(
            new SignedWith(new $algorithmClass(), $key)
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $token): array
    {
        try {
            $token = $this->configuration->parser()->parse($token);

            $constraints = $this->configuration->validationConstraints();

            if (
                !$this->configuration->validator()->validate(
                    $token,
                    ...$constraints
                )
            ) {
                throw new DomainException('Token invalid at this time');
            }

            return $token->claims()->all();
        } catch (Throwable $e) {
            throw new TokenException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
