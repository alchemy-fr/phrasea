<?php

declare(strict_types=1);

namespace App\Form\Resolver;

interface WidgetResolverInterface
{
    public function supports(array $options): bool;

    public function getFormType(array $options): string;

    public function getFormOptions(array $options): array;
}
