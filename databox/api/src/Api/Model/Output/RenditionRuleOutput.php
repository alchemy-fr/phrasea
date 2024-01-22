<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Entity\Core\RenditionRule;
use Symfony\Component\Serializer\Annotation\Groups;

class RenditionRuleOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;

    #[Groups([RenditionRule::GROUP_LIST])]
    private ?string $userId = null;

    #[Groups([RenditionRule::GROUP_LIST])]
    public ?UserOutput $user = null;

    #[Groups([RenditionRule::GROUP_LIST])]
    private ?string $groupId = null;

    #[Groups([RenditionRule::GROUP_LIST])]
    public ?GroupOutput $group = null;

    #[Groups([RenditionRule::GROUP_LIST])]
    private ?string $workspaceId = null;

    #[Groups([RenditionRule::GROUP_LIST])]
    private ?string $collectionId = null;

    #[Groups([RenditionRule::GROUP_LIST])]
    private ?array $allowed = null;

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

    public function getAllowed(): ?array
    {
        return $this->allowed;
    }

    public function setAllowed(?array $allowed): void
    {
        $this->allowed = $allowed;
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
}
