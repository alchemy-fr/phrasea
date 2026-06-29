<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AssetPolicyOutput;
use App\Api\Model\Output\GroupOutput;
use App\Api\Model\Output\UserOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;

class AssetPolicyOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserLocaleTrait;
    use UserOutputTransformerTrait;
    use GroupOutputTransformerTrait;

    public function supports(string $outputClass, object $data): bool
    {
        return AssetPolicyOutput::class === $outputClass && $data instanceof AssetPolicy;
    }

    /**
     * @param AssetPolicy $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new AssetPolicyOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setId($data->getId());
        $output->workspace = $data->getWorkspace();
        $output->name = $data->getName();
        $output->users = array_map(function (string $userId): UserOutput {
            return $this->transformUser($userId);
        }, $data->getUserIdsOfType(AssetPolicyUser::TYPE_USER));
        $output->groups = array_map(function (string $groupId): GroupOutput {
            return $this->transformGroup($groupId);
        }, $data->getUserIdsOfType(AssetPolicyUser::TYPE_GROUP));

        $output->conditions = $data->getConditions();
        $output->actions = $data->getActions();

        return $output;
    }
}
