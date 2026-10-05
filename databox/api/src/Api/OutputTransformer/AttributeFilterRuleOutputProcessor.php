<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Repository\GroupRepository;
use Alchemy\AuthBundle\Repository\UserRepository;
use App\Api\Model\Output\AttributeFilterRuleOutput;
use App\Entity\Core\AttributeFilterRule;

final readonly class AttributeFilterRuleOutputProcessor implements OutputTransformerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private GroupRepository $groupRepository,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return AttributeFilterRuleOutput::class === $outputClass && $data instanceof AttributeFilterRule;
    }

    /**
     * @param AttributeFilterRule $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
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
