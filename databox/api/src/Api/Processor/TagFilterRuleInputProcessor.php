<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\TagFilterRuleInput;
use App\Entity\Core\TagFilterRule;

class TagFilterRuleInputProcessor implements ProcessorInterface
{
    /**
     * @param TagFilterRuleInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        $tagFilterRule = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new TagFilterRule();

        if ($data->collectionId) {
            $tagFilterRule->setObjectType(TagFilterRule::TYPE_COLLECTION);
            $tagFilterRule->setObjectId($data->collectionId);
        } elseif ($data->workspaceId) {
            $tagFilterRule->setObjectType(TagFilterRule::TYPE_WORKSPACE);
            $tagFilterRule->setObjectId($data->workspaceId);
        } elseif ($isNew) {
            throw new \InvalidArgumentException('Missing collectionId or workspaceId');
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
}
