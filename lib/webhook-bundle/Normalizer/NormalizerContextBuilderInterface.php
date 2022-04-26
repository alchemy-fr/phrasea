<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Normalizer;

interface NormalizerContextBuilderInterface
{
    public function buildContext(array $context = []): array;
}
