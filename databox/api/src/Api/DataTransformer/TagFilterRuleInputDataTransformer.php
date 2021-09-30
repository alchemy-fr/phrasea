<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\TagFilterRuleInput;
use App\Entity\Core\TagFilterRule;
use InvalidArgumentException;

class TagFilterRuleInputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param TagFilterRuleInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $tagFilterRule = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new TagFilterRule();

        if ($data->collectionId) {
            $tagFilterRule->setObjectType(TagFilterRule::TYPE_COLLECTION);
            $tagFilterRule->setObjectId($data->collectionId);
        } elseif ($data->workspaceId) {
            $tagFilterRule->setObjectType(TagFilterRule::TYPE_WORKSPACE);
            $tagFilterRule->setObjectId($data->workspaceId);
        } else {
            throw new InvalidArgumentException('Missing collectionId or workspaceId');
        }

        if ($data->groupId) {
            $tagFilterRule->setUserType(TagFilterRule::TYPE_GROUP);
            $tagFilterRule->setUserId($data->groupId);
        } elseif ($data->userId) {
            $tagFilterRule->setUserType(TagFilterRule::TYPE_USER);
            $tagFilterRule->setUserId($data->userId);
        } else {
            throw new InvalidArgumentException('Missing groupId or userId');
        }

        $collection = $tagFilterRule->getInclude();
        $collection->clear();
        foreach ($data->include as $rule) {
            $collection->add($rule);
        }

        $collection = $tagFilterRule->getExclude();
        $collection->clear();
        foreach ($data->exclude as $rule) {
            $collection->add($rule);
        }

        return $tagFilterRule;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof TagFilterRule) {
            return false;
        }

        return TagFilterRule::class === $to && TagFilterRuleInput::class === ($context['input']['class'] ?? null);
    }
}
