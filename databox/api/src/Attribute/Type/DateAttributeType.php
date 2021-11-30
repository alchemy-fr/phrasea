<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;

class DateAttributeType extends AbstractAttributeType
{
    public static function getName(): string
    {
        return 'date';
    }

    public function getElasticSearchType(): string
    {
        return 'date';
    }

    public function getElasticSearchMapping(string $language): array
    {
        return [
            'fields' => [
                'text' => [
                    'type' => 'text',
                ]
            ]
        ];
    }

    /**
     * @param string|DateTimeInterface $value
     *
     * @return string|null
     */
    public function normalizeValue($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            if (empty(trim($value))) {
                return null;
            }
            try {
                $value = new DateTimeImmutable($value, new  DateTimeZone('UTC'));
            } catch (Throwable $e) {
                return null;
            }
        } elseif (!$value instanceof DateTimeInterface) {
            return null;
        }

        $str = $value->format(DateTimeInterface::ATOM);

        return preg_replace('#\+00:00$#', 'Z', $str);
    }

    /**
     * @param string $value
     *
     * @return DateTimeImmutable|null
     */
    public function denormalizeValue($value)
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            return;
        }

        try {
            new DateTimeImmutable($value);
        } catch (\Exception $e) {
            $context->addViolation('Invalid date');

            return;
        }
    }
}
