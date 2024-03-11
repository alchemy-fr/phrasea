<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Repository\GroupRepositoryInterface;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Api\Model\Output\GroupOutput;
use App\Api\Model\Output\RenditionRuleOutput;
use App\Api\Model\Output\UserOutput;
use App\Entity\Core\RenditionRule;

readonly class RenditionRuleOutputProcessor implements OutputTransformerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return RenditionRuleOutput::class === $outputClass && $data instanceof RenditionRule;
    }

    /**
     * @param RenditionRule $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new RenditionRuleOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        if (RenditionRule::TYPE_USER === $data->getUserType()) {
            $output->setUserId($data->getUserId());
            $user = $this->userRepository->getUser($data->getUserId());
            $userOutput = new UserOutput();
            $userOutput->id = $user['id'];
            $userOutput->username = $user['username'];
            $output->user = $userOutput;
        } elseif (RenditionRule::TYPE_GROUP === $data->getUserType()) {
            $output->setGroupId($data->getUserId());
            $group = $this->groupRepository->getGroup($data->getUserId());
            $groupOutput = new GroupOutput();
            $groupOutput->id = $group['id'];
            $groupOutput->name = $group['name'];
            $output->group = $groupOutput;
        }

        if (RenditionRule::TYPE_COLLECTION === $data->getObjectType()) {
            $output->setCollectionId($data->getObjectId());
        } elseif (RenditionRule::TYPE_WORKSPACE === $data->getObjectType()) {
            $output->setWorkspaceId($data->getObjectId());
        }

        $output->setAllowed($data->getAllowed()->getValues());

        return $output;
    }
}
