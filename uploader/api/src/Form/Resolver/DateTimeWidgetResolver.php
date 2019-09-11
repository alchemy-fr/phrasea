<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class DateTimeWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return DateTimeType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [
            'widget' => 'single_text',
        ];
    }

    public function supports(array $config): bool
    {
        return 'date-time' === $config['format']
            || in_array($config['widget'], [
                'datetime',
                'compatible-datetime',
            ], true);
    }
}
