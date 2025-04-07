<?php

namespace App\Elasticsearch\AQL;

final readonly class AQLFunctionHandler
{
    public static function parseFunction(&$result): void
    {
        $args = [];
        if (isset($result['first'])) {
            $args[] = $result['first']['data'];
        }

        if (isset($result['others']['_matchrule'])) {
            $args[] = $result['others']['data'];
        } else {
            foreach ($result['others'] ?? [] as $v) {
                $args[] = $v['data'];
            }
        }

        $result['data'] = [
            'type' => 'function_call',
            'function' => $result['f']['text'],
            'arguments' => $args,
        ];

        unset($result['f'], $result['first'], $result['others']);
    }
}
