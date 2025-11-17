<?php

namespace App\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\VarDateTimeImmutableType;

class DatetimeImmutableMicroseconds extends VarDateTimeImmutableType
{
    public const NAME = 'datetime_immutable';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'DATETIME(6)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeInterface
    {
        if ($value === null || $value instanceof \DateTimeInterface) {
            return $value;
        }

        $datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $value);

        if ($datetime === false) {
            $datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        }

        return $datetime ?: null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u');
        }

        return $value;
    }

}
