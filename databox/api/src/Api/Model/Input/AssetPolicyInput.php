<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\AssetPolicy\AssetPolicy;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class AssetPolicyInput
{
    #[Groups([AssetPolicy::GROUP_WRITE])]
    #[Assert\NotBlank(groups: ['create'])]
    public ?string $name = null;

    #[Groups([AssetPolicy::GROUP_WRITE])]
    #[Assert\Count(max: 30)]
    public ?array $users = null;

    #[Groups([AssetPolicy::GROUP_WRITE])]
    #[Assert\Count(max: 30)]
    public ?array $groups = null;

    #[Groups([AssetPolicy::GROUP_WRITE])]
    #[Assert\NotNull(groups: ['create'])]
    public ?string $workspaceId = null;

    #[Groups([AssetPolicy::GROUP_WRITE])]
    #[Assert\NotNull(groups: ['create'])]
    #[Assert\Count(min: 0, max: 100)]
    public ?array $conditions = null;

    #[Groups([AssetPolicy::GROUP_WRITE])]
    #[Assert\NotNull(groups: ['create'])]
    #[Assert\Count(min: 1, max: 100)]
    public ?array $actions = null;

    #[Assert\Callback(groups: ['create'])]
    public function validate(ExecutionContextInterface $context): void
    {
        if (empty($this->users) && empty($this->groups)) {
            $context->buildViolation('At least one user or one group is required.')
                ->atPath('users')
                ->addViolation();
        }
    }
}
