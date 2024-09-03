<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Entity\Core\TagFilterRule;
use Symfony\Component\Serializer\Annotation\Groups;

class TagFilterRuleOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;

    #[Groups([TagFilterRule::GROUP_READ])]
    private ?string $userId = null;
    #[Groups([TagFilterRule::GROUP_READ])]
    private ?string $username = null;

    #[Groups([TagFilterRule::GROUP_READ])]
    private ?string $groupId = null;

    #[Groups([TagFilterRule::GROUP_READ])]
    private ?string $groupName = null;

    #[Groups([TagFilterRule::GROUP_READ])]
    private ?string $workspaceId = null;

    #[Groups([TagFilterRule::GROUP_READ])]
    private ?string $collectionId = null;

    #[Groups([TagFilterRule::GROUP_READ])]
    private ?array $include = null;

    #[Groups([TagFilterRule::GROUP_READ])]
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

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $groupName): void
    {
        $this->groupName = $groupName;
    }
}
