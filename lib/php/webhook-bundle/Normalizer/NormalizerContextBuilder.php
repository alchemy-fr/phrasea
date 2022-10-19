<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Normalizer;

class NormalizerContextBuilder implements NormalizerContextBuilderInterface
{
    public function buildContext(array $context = []): array
    {
        return $context;
    }
}
