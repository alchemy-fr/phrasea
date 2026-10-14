<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AuthBundle\Repository\GroupRepository;
use Alchemy\AuthBundle\Repository\UserRepository;
use App\Api\Model\Output\AttributeFilterRuleOutput;
use App\Entity\Core\AttributeFilterRule;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AttributeFilterRuleOutput::class)]
final readonly class AttributeFilterRuleOutputMapper implements OutputMapperInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private GroupRepository $groupRepository,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof AttributeFilterRule;
    }

    /**
     * @param AttributeFilterRule $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AttributeFilterRuleOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setWorkspaceId($data->getWorkspaceId());
        $output->setCondition($data->getCondition());

        $output->setUsers(array_map(function (string $userId): array {
            $user = $this->userRepository->getUser($userId);

            return [
                'id' => $userId,
                'name' => $user ? $user['username'] : 'User not found',
            ];
        }, $data->getUserIds()));

        $output->setGroups(array_map(function (string $groupId): array {
            $group = $this->groupRepository->getGroup($groupId);

            return [
                'id' => $groupId,
                'name' => $group ? $group['name'] : 'Group not found',
            ];
        }, $data->getGroupIds()));

        return $output;
    }
}
