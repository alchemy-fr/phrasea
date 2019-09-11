<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\EmailType;

class EmailWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return EmailType::class;
    }

    public function getFormOptions(array $config): array
    {
        return [];
    }

    public function supports(array $config): bool
    {
        return 'email' === $config['format'] || 'email' === $config['widget'];
    }
}
