<?php

declare(strict_types=1);

namespace App\Service\Log;

use Ramsey\Uuid\UuidInterface;

/**
 * Turns the values of an audit log entry into JSON: an entity becomes its ID, a date its ATOM string
 * (as in AbstractChangeLog::setChanges()), an enum its value.
 */
final class AuditValueNormalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        if (!is_object($value)) {
            return $value;
        }

        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof UuidInterface => $value->toString(),
            method_exists($value, 'getId') => (string) $value->getId(),
            $value instanceof \Stringable => (string) $value,
            default => $value::class,
        };
    }
}
