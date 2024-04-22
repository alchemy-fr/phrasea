<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CheckboxWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return CheckboxType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [];
    }

    public function supports(array $config): bool
    {
        return in_array($config['type'], [
            'boolean',
        ], true);
    }
}
