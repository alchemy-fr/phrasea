<?php

declare(strict_types=1);

namespace App\Elasticsearch;

class QueryStringParser
{
    public function parseQuery(string $query): array
    {
        $filters = [];
        $query = preg_replace_callback('/(^|\s)in:(all|trash)/s', function (array $r) use (&$filters): string {
            $filters[] = ['in' => $r[2]];

            return '';
        }, $query);

        $must = [];
        $should = preg_replace_callback('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s', function (array $r) use (&$must): string {
            $must[] = substr($r[0], 1, -1);

            return '';
        }, $query);

        $should = $this->normalizeString($should);

        $must = array_map(fn (string $str): string => $this->normalizeString($str), $must);

        return [
            'must' => $must,
            'should' => $should,
            'filters' => $filters,
        ];
    }

    private function normalizeString(string $str): string
    {
        return trim(preg_replace('/\s{2,}/', ' ', $str));
    }
}
