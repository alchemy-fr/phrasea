<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Api\Model\Input\AttributeFilterRuleInput;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AttributeFilterRuleInput::class)]
class AttributeFilterRuleInputMapper implements InputMapperInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param AttributeFilterRuleInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        $attributeFilterRule = $target ?? new AttributeFilterRule();

        if ($data->workspaceId) {
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $data->workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);
            $attributeFilterRule->setWorkspace($workspace);
        } elseif ($isNew) {
            throw new \InvalidArgumentException('Missing workspaceId');
        }

        if (null !== $data->userIds || null !== $data->groupIds || $isNew) {
            $attributeFilterRule->setTargets($data->userIds ?? [], $data->groupIds ?? []);
        }

        if (null !== $data->condition) {
            $attributeFilterRule->setCondition($data->condition);
        }

        return $attributeFilterRule;
    }
}
