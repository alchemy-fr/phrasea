<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\TagFilterRuleOutput;
use App\Api\Model\Output\TagOutput;
use App\Entity\Core\Tag;
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
        if ($object->getUserType() === TagFilterRule::TYPE_USER) {
            $output->setUserId($object->getUserId());
        } elseif ($object->getUserType() === TagFilterRule::TYPE_GROUP) {
            $output->setGroupId($object->getUserId());
        }

        if ($object->getObjectType() === TagFilterRule::TYPE_COLLECTION) {
            $output->setCollectionId($object->getObjectId());
        } elseif ($object->getObjectType() === TagFilterRule::TYPE_WORKSPACE) {
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
