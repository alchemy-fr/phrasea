<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;

final readonly class GroupNormalizerContextBuilder implements NormalizerContextBuilderInterface
{
    public function __construct(private NormalizerContextBuilderInterface $decorated)
    {
    }

    public function buildContext(array $context = []): array
    {
        if (isset($context['groups'])) {
            foreach ($context['groups'] as $group) {
                if (1 === preg_match('#^([^:]+):read$#', (string) $group, $matches)) {
                    $context['groups'][] = $matches[1].':index';
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

        return $this->decorated->buildContext($context);
    }
}
