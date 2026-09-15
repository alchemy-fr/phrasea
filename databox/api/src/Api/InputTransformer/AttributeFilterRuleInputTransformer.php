<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Api\Model\Input\AttributeFilterRuleInput;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AttributeFilterRuleInputTransformer implements InputTransformerInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param AttributeFilterRuleInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        $attributeFilterRule = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AttributeFilterRule();

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

    public function supports(string $resourceClass, object $data): bool
    {
        return AttributeFilterRule::class === $resourceClass && $data instanceof AttributeFilterRuleInput;
    }
}
