<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Entity\Core\AttributeFilterRule;
use Symfony\Component\Serializer\Annotation\Groups;

class AttributeFilterRuleOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;

    /**
     * @var array<array{id: string, name: string}>
     */
    #[Groups([AttributeFilterRule::GROUP_READ])]
    private array $users = [];

    /**
     * @var array<array{id: string, name: string}>
     */
    #[Groups([AttributeFilterRule::GROUP_READ])]
    private array $groups = [];

    #[Groups([AttributeFilterRule::GROUP_READ])]
    private ?string $workspaceId = null;

    #[Groups([AttributeFilterRule::GROUP_READ])]
    private ?string $condition = null;

    public function getUsers(): array
    {
        return $this->users;
    }

    public function setUsers(array $users): void
    {
        $this->users = $users;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspaceId;
    }

    public function setWorkspaceId(?string $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): void
    {
        $this->condition = $condition;
    }
}
