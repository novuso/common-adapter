<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine\DataType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Exception;
use Novuso\Common\Domain\Value\DateTime\Time;
use Throwable;

/**
 * Class TimeDataType
 */
final class TimeDataType extends Type
{
    /**
     * Type name
     *
     * @var string
     */
    public const TYPE_NAME = 'common_time';

    /**
     * Gets the SQL declaration snippet for a field of this type
     *
     * @throws Exception
     */
    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform
    ): string {
        return $platform->getTimeTypeDeclarationSQL($column);
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
            throw ConversionException::conversionFailedInvalidType(
                $value,
                'string',
                [Time::class]
            );
        }

        return sprintf(
            '%02d:%02d:%02d',
            $value->hour(),
            $value->minute(),
            $value->second()
        );
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
            $hour = (int) substr($value, 0, 2);
            $minute = (int) substr($value, 3, 2);
            $second = (int) substr($value, 6, 2);

            return Time::create($hour, $minute, $second);
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
