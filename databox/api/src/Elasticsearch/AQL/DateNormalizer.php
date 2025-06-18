<?php

namespace App\Elasticsearch\AQL;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class DateNormalizer
{
    public function normalizeDate(mixed $value, bool $allowPartialDate = false, bool $withTimeZone = true): int|string|array
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
            if (10 === $length) {
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
                        if (false === $date && !$allowPartialDate) {
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
                        if (false === $date && !$allowPartialDate) {
                            $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
                            if (false === $date) {
                                $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:iO', $value);
                            }
                        }
                    }
                }

                if ($date instanceof \DateTimeImmutable) {
                    $tz = $withTimeZone ? 'O' : '';
                    $format = $withMicro ? 'Y-m-d\TH:i:s.u'.$tz : 'Y-m-d\TH:i:s'.$tz;

                    return $date->format($format);
                }
            }

            if ($allowPartialDate && preg_match('/^\d{4}(-\d{2}(-\d{2})?([T\s]\d{2}(:\d{2})?(:\d{2})?)?)?$/', $originalValue)) {
                return str_replace(' ', 'T', $originalValue);
            }

            throw new BadRequestHttpException(sprintf('Invalid date value "%s"', $originalValue));
        }

        throw new BadRequestHttpException(sprintf('Invalid date type "%s"', get_debug_type($originalValue)));
    }
}
