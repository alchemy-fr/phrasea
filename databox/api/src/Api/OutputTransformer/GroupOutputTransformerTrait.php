<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Repository\GroupRepositoryInterface;
use App\Api\Model\Output\GroupOutput;
use Symfony\Contracts\Service\Attribute\Required;

trait GroupOutputTransformerTrait
{
    private GroupRepositoryInterface $groupRepository;

    protected function transformGroup(?string $groupId): ?GroupOutput
    {
        if (null === $groupId) {
            return null;
        }

        $output = new GroupOutput();
        $output->id = $groupId;

        $group = $this->groupRepository->getGroup($groupId);
        if (null !== $group) {
            $output->name = $group['name'];
        } else {
            $output->name = 'Group not found';
            $output->removed = true;
        }

        return $output;
    }

    #[Required]
    public function setGroupRepository(GroupRepositoryInterface $groupRepository): void
    {
        $this->groupRepository = $groupRepository;
    }
}
