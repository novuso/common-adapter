<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine\DataType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Novuso\Common\Domain\Value\Identifier\Uuid;
use Throwable;

/**
 * Class UuidDataType
 */
final class UuidDataType extends Type
{
    public const TYPE_NAME = 'common_uuid';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform
    ): string {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    /**
     * Converts a value from its PHP representation to its database representation
     *
     * @throws ConversionException When the conversion fails
     */
    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform
    ): ?string {
        if (empty($value)) {
            return null;
        }

        if (!($value instanceof Uuid)) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                'string',
                [Uuid::class]
            );
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform
    ): ?Uuid {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Uuid) {
            return $value;
        }

        try {
            return Uuid::fromString($value);
        } catch (Throwable $e) {
            throw ConversionException::conversionFailed(
                $value,
                static::TYPE_NAME
            );
        }
    }

    /**
     * Gets the name of this type
     */
    public function getName(): string
    {
        return static::TYPE_NAME;
    }

    /**
     * Checks if this type requires a SQL comment hint
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
