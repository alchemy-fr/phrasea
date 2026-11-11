<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Sort key giving a natural, case and accent insensitive order ("Lot 2" before "Lot 10", "Été" next to "ete")
 * when keys are compared by code points: strcmp() in PHP, keyword sort and painless String comparison in
 * Elasticsearch, compareCodePoints() in the databox client (lib/utils/naturalSortKey.ts mirrors this function).
 *
 * Every digit run is prefixed with its length on two digits, leading zeros stripped: "007" => "017", "10" => "0210".
 *
 * Known limits: ligatures and "ß" are not folded, only ASCII digits are numbers, "Lot 02" and "Lot 2" get the
 * same key (the caller breaks the tie).
 */
final class NaturalSortKey
{
    /**
     * In code points, far below the Lucene limit of 32766 bytes for a keyword term.
     */
    public const int MAX_LENGTH = 1000;

    public static function fromString(?string $s): string
    {
        if (null === $s || '' === $s) {
            return '';
        }

        $k = \Normalizer::normalize($s, \Normalizer::FORM_D);
        if (false === $k) {
            $k = $s;
        }
        $k = mb_strtolower($k, 'UTF-8');
        $k = preg_replace('/\p{Mn}+/u', '', $k) ?? $k;
        $k = preg_replace('/[\p{Z}\x{0009}-\x{000D}]+/u', ' ', $k) ?? $k;
        $k = trim($k, ' ');
        $k = preg_replace_callback('/[0-9]+/', static function (array $m): string {
            $d = ltrim($m[0], '0');
            if ('' === $d) {
                $d = '0';
            }

            return sprintf('%02d', min(strlen($d), 99)).$d;
        }, $k) ?? $k;

        return mb_substr($k, 0, self::MAX_LENGTH, 'UTF-8');
    }

    /**
     * Code point order, the one of Elasticsearch on keywords.
     */
    public static function compare(string $a, string $b): int
    {
        return strcmp($a, $b) <=> 0;
    }
}
