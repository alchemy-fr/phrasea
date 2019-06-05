<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\EmailType;

class EmailWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $options): string
    {
        return EmailType::class;
    }

    public function getFormOptions(array $options): array
    {
        return [
        ];
    }

    public function supports(array $config): bool
    {
        $widget = $config['widget'] ?? 'text';
        $type = $config['type'] ?? 'string';

        return in_array($type, [
                'string',
            ], true)
            && in_array($widget, [
                'text', 'email', 'tel', 'password',
            ], true);
    }
}
