<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use Symfony\Component\Serializer\Annotation\Groups;

class TagFilterRuleOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;

    #[Groups(['tfr:read'])]
    private ?string $userId = null;

    #[Groups(['tfr:read'])]
    private ?string $groupId = null;

    #[Groups(['tfr:read'])]
    private ?string $workspaceId = null;

    #[Groups(['tfr:read'])]
    private ?string $collectionId = null;

    #[Groups(['tfr:read'])]
    private ?array $include = null;

    #[Groups(['tfr:read'])]
    private ?array $exclude = null;

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getGroupId(): ?string
    {
        return $this->groupId;
    }

    public function setGroupId(?string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspaceId;
    }

    public function setWorkspaceId(?string $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
    }

    public function getCollectionId(): ?string
    {
        return $this->collectionId;
    }

    public function setCollectionId(?string $collectionId): void
    {
        $this->collectionId = $collectionId;
    }

    public function getInclude(): ?array
    {
        return $this->include;
    }

    public function setInclude(?array $include): void
    {
        $this->include = $include;
    }

    public function getExclude(): ?array
    {
        return $this->exclude;
    }

    public function setExclude(?array $exclude): void
    {
        $this->exclude = $exclude;
    }
}
