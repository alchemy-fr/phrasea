<?php

declare(strict_types=1);

namespace App\Integration;

interface IntegrationInterface
{
    public static function getName(): string;
}
