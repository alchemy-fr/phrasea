<?php

declare(strict_types=1);

namespace App\Serializer;

final readonly class GroupNormalizerContextBuilder
{
    public function buildContext(array $context = []): array
    {
        if (isset($context['groups'])) {
            foreach ($context['groups'] as $group) {
                if (1 === preg_match('#^([^:]+):r$#', (string) $group, $matches)) {
                    $context['groups'][] = $matches[1].':i';
                }
            }
            $context['_level'] ??= 0;
            if (0 === $context['_level']) {
                $context['groups'][] = '_';
                $context['groups'][] = 'dates';
            }
            ++$context['_level'];
            $context['groups'] = array_unique($context['groups']);
        }

        if ('post_multiple' === ($context['collection_operation_name'] ?? null)) {
            $context['api_sub_level'] = true; // prevent Hydra collection in MultipleAssetOutput
        }

        return $context;
    }
}
