<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine\DataType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Novuso\Common\Domain\Value\DateTime\Time;
use Throwable;

/**
 * Class TimeStringDataType
 */
final class TimeStringDataType extends Type
{
    public const TYPE_NAME = 'common_time_string';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform
    ): string {
        if (!isset($column['length'])) {
            $column['length'] = 15;
        }

        return $platform->getStringTypeDeclarationSQL($column);
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

        if (!($value instanceof Time)) {
            throw InvalidType::new($value, 'string', [Time::class]);
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
    ): ?Time {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Time) {
            return $value;
        }

        try {
            return Time::fromString($value);
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, Time::class, $e->getMessage(), $e);
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
