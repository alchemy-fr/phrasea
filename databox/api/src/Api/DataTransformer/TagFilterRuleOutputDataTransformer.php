<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\TagFilterRuleOutput;
use App\Entity\Core\TagFilterRule;

class TagFilterRuleOutputDataTransformer implements DataTransformerInterface
{
    /**
     * @param TagFilterRule $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new TagFilterRuleOutput();
        $output->setId($object->getId());
        $output->setCreatedAt($object->getCreatedAt());
        if (TagFilterRule::TYPE_USER === $object->getUserType()) {
            $output->setUserId($object->getUserId());
        } elseif (TagFilterRule::TYPE_GROUP === $object->getUserType()) {
            $output->setGroupId($object->getUserId());
        }

        if (TagFilterRule::TYPE_COLLECTION === $object->getObjectType()) {
            $output->setCollectionId($object->getObjectId());
        } elseif (TagFilterRule::TYPE_WORKSPACE === $object->getObjectType()) {
            $output->setWorkspaceId($object->getObjectId());
        }

        $output->setInclude($object->getInclude()->getValues());
        $output->setExclude($object->getExclude()->getValues());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return TagFilterRuleOutput::class === $to && $data instanceof TagFilterRule;
    }
}
