<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\DateType;

class DateWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return DateType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [
            'widget' => 'single_text',
        ];
    }

    public function supports(array $config): bool
    {
        return 'date' === $config['format']
            || in_array($config['widget'], [
                'date',
                'compatible-date',
            ], true);
    }
}
