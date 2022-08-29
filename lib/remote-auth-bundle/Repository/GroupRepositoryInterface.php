<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Repository;

interface GroupRepositoryInterface
{
    public function getGroups(?int $limit = null, ?int $offset = null): array;
}
