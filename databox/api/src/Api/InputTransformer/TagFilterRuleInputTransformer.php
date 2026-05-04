<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Api\Model\Input\TagFilterRuleInput;
use App\Entity\Core\TagFilterRule;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class TagFilterRuleInputTransformer implements InputTransformerInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param TagFilterRuleInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        $tagFilterRule = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new TagFilterRule();

        if ($data->workspaceId) {
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $data->workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);
            $tagFilterRule->setWorkspace($workspace);
        } elseif ($isNew) {
            throw new \InvalidArgumentException('Missing workspaceId');
        }

        if ($data->groupId) {
            $tagFilterRule->setUserType(TagFilterRule::TYPE_GROUP);
            $tagFilterRule->setUserId($data->groupId);
        } elseif ($data->userId) {
            $tagFilterRule->setUserType(TagFilterRule::TYPE_USER);
            $tagFilterRule->setUserId($data->userId);
        }

        $collection = $tagFilterRule->getInclude();
        $collection->clear();
        foreach ($data->include ?? [] as $rule) {
            $collection->add($rule);
        }

        $collection = $tagFilterRule->getExclude();
        $collection->clear();
        foreach ($data->exclude ?? [] as $rule) {
            $collection->add($rule);
        }

        return $tagFilterRule;
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return TagFilterRule::class === $resourceClass && $data instanceof TagFilterRuleInput;
    }
}
