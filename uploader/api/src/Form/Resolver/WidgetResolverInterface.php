<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface WidgetResolverInterface
{
    final public const string TAG = 'app.widget_resolver';

    public function supports(array $config): bool;

    public function getFormType(array $config): string;

    public function getFormOptions(array $config): array;
}
