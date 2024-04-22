<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class TextWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return TextType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [];
    }

    public function supports(array $config): bool
    {
        return in_array($config['type'], [
            'string',
        ], true)
            && in_array($config['widget'], [
                'text',
            ], true);
    }
}
