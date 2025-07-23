<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use App\Form\LiFormWidgetResolver;
use App\Form\Type\ArrayType;

class ArrayWidgetResolver implements WidgetResolverInterface
{
    public function __construct(
        private readonly LiFormWidgetResolver $widgetResolver,
    ) {
    }

    public function getFormType(array $config): string
    {
        return ArrayType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [
            'entry_type' => $this->widgetResolver->getFormType($config['items']),
            'entry_options' => $this->widgetResolver->getFieldOptions($config['items']),
        ];
    }

    public function supports(array $config): bool
    {
        return 'array' === $config['type']
            && !isset($config['items']['enum']);
    }
}
