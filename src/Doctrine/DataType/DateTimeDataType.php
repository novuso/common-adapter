<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Doctrine\DataType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Exception;
use Novuso\Common\Domain\Value\DateTime\DateTime;
use Novuso\System\Exception\DomainException;
use Throwable;

/**
 * Class DateTimeDataType
 */
final class DateTimeDataType extends Type
{
    public const TYPE_NAME = 'common_date_time';

    /**
     * Gets the SQL declaration snippet for a field of this type
     *
     * @throws Exception
     */
    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform
    ): string {
        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    /**
     * Converts a value from its PHP representation to its database representation
     *
     * @throws ConversionException When the conversion fails
     * @throws DomainException When default timezone is invalid
     */
    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform
    ): ?string {
        if (empty($value)) {
            return null;
        }

        if (!($value instanceof DateTime)) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                'string',
                [DateTime::class]
            );
        }

        // @codeCoverageIgnoreStart
        if ($value->timezone()->toString() !== date_default_timezone_get()) {
            $value = $value->toTimezone(date_default_timezone_get());
        }
        // @codeCoverageIgnoreEnd

        return $value->format('Y-m-d H:i:s');
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform
    ): ?DateTime {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value;
        }

        try {
            $year = (int) substr($value, 0, 4);
            $month = (int) substr($value, 5, 2);
            $day = (int) substr($value, 8, 2);
            $hour = (int) substr($value, 11, 2);
            $minute = (int) substr($value, 14, 2);
            $second = (int) substr($value, 17, 2);

            return DateTime::create(
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second
            );
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
