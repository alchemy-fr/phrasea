<?php

declare(strict_types=1);

namespace App\Integration;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface IntegrationInterface
{
    public static function getName(): string;

    public function configureOptions(OptionsResolver $resolver): void;
}
