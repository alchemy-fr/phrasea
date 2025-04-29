<?php

namespace App\Elasticsearch\AQL;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class DateNormalizer
{
    public function normalizeDate(mixed $value): int|string|array
    {
        $originalValue = $value;
        if (is_array($value)) {
            return array_map(function ($v) {
                return $this->normalizeDate($v);
            }, $value);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if (is_numeric($value)) {
                return (int) $value;
            }

            $length = strlen($value);
            if ($length === 10) {
                $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
                if ($date instanceof \DateTimeImmutable) {
                    return $date->format('Y-m-d');
                }
            } elseif ($length > 10) {
                if (!str_contains($value, 'T')) {
                    $value = str_replace(' ', 'T', $value);
                }

                $withMicro = str_contains($value, '.');
                if ($withMicro) {
                    $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uO', $value);
                    if (false === $date) {
                        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $value);
                        if (false === $date) {
                            $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i.uO', $value);
                            if (false === $date) {
                                $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i.u', $value);
                            }
                        }
                    }
                } else {
                    $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sO', $value);
                    if (false === $date) {
                        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
                        if (false === $date) {
                            $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
                            if (false === $date) {
                                $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:iO', $value);
                            }
                        }
                    }
                }

                if ($date instanceof \DateTimeImmutable) {
                    $format = $withMicro ? 'Y-m-d\TH:i:s.uO' : 'Y-m-d\TH:i:sO';
                    return $date->format($format);
                }
            }

            throw new BadRequestHttpException(sprintf('Invalid date value "%s"', $originalValue));
        }

        throw new BadRequestHttpException(sprintf('Invalid date type "%s"', get_debug_type($originalValue)));
    }
}
