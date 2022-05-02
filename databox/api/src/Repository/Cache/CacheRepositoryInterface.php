<?php

declare(strict_types=1);

namespace App\Repository\Cache;

interface CacheRepositoryInterface
{
    public function invalidateEntity(string $id): void;

    public function invalidateList(): void;
}
