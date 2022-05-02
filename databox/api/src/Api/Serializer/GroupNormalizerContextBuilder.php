<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;

class GroupNormalizerContextBuilder implements NormalizerContextBuilderInterface
{
    private NormalizerContextBuilderInterface $decorated;

    public function __construct(NormalizerContextBuilderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function buildContext(array $context = []): array
    {
        if (isset($context['groups'])) {
            $context['groups'][] = '_';
            foreach ($context['groups'] as $group) {
                if (1 === preg_match('#^([^:]+):read$#', $group, $matches)) {
                    $context['groups'][] = $matches[1].':index';
                }
            }
            $context['_level'] = $context['_level'] ?? 0;
            if (0 === $context['_level']) {
                $context['groups'][] = 'dates';
            }
            ++$context['_level'];
            $context['groups'] = array_unique($context['groups']);
        }

        return $this->decorated->buildContext($context);
    }
}
