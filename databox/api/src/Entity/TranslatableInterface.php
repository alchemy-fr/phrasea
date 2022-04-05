<?php

declare(strict_types=1);

namespace App\Entity;

interface TranslatableInterface
{
    // TODO
//    public static function getTranslatableProperties(): array;

    public function getLocale(): string;

    public function hasLocale(): bool;

    public function setLocale(string $locale): void;
}
