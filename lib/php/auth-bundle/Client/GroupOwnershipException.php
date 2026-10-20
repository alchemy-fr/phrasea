<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Client;

final class GroupOwnershipException extends \RuntimeException
{
    public function __construct(private readonly string $groupPath)
    {
        parent::__construct(sprintf('Group "%s" already exists and is not managed by this application. Adopt it explicitly to reuse it.', $groupPath));
    }

    public function getGroupPath(): string
    {
        return $this->groupPath;
    }
}
