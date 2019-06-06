<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\NumberType;

class NumberWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return NumberType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [];
    }

    public function supports(array $config): bool
    {
        return 'number' === $config['format'] || 'number' === $config['widget'];
    }
}
