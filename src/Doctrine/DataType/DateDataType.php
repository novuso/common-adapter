<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine\DataType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Exception;
use Novuso\Common\Domain\Value\DateTime\Date;
use Throwable;

/**
 * Class DateDataType
 */
final class DateDataType extends Type
{
    public const TYPE_NAME = 'common_date';

    /**
     * Gets the SQL declaration snippet for a field of this type
     *
     * @throws Exception
     */
    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform
    ): string {
        return $platform->getDateTypeDeclarationSQL($column);
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

        if (!($value instanceof Date)) {
            throw InvalidType::new($value, 'string', [Date::class]);
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
    ): ?Date {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Date) {
            return $value;
        }

        try {
            return Date::fromString($value);
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, Date::class, $e->getMessage(), $e);
        }
    }

    /**
     * Gets the name of this type
     */
    public function getName(): string
    {
        return static::TYPE_NAME;
    }
}
