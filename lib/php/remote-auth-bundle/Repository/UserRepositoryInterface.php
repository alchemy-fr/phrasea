<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

interface UserRepositoryInterface
{
    public function getUsers(?int $limit = null, ?int $offset = null): array;
}
