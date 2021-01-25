<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Security;

use DateTimeImmutable;
use DateTimeInterface;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Novuso\Common\Application\Security\Exception\TokenException;
use Novuso\Common\Application\Security\TokenEncoder;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\System\Exception\DomainException;
use Throwable;

/**
 * Class JwtEncoder
 */
final class JwtEncoder implements TokenEncoder
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
     * Constructs JwtEncoder
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
    }

    /**
     * @inheritDoc
     */
    public function encode(array $claims, DateTime $expiration): string
    {
        try {
            $expiresAt = DateTimeImmutable::createFromFormat(
                DateTimeInterface::ATOM,
                $expiration->format(DateTimeInterface::ATOM)
            );

            $builder = $this->configuration->builder()
                ->expiresAt($expiresAt);

            foreach ($claims as $key => $value) {
                $builder = $builder->withClaim($key, $value);
            }

            $token = $builder->getToken(
                $this->configuration->signer(),
                $this->configuration->signingKey()
            );

            return $token->toString();
        } catch (Throwable $e) {
            throw new TokenException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
