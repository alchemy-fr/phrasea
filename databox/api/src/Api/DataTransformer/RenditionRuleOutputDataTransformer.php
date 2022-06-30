<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use App\Api\Model\Output\RenditionRuleOutput;
use App\Entity\Core\RenditionRule;

class RenditionRuleOutputDataTransformer implements DataTransformerInterface
{
    /**
     * @param RenditionRule $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new RenditionRuleOutput();
        $output->setId($object->getId());
        $output->setCreatedAt($object->getCreatedAt());
        if (RenditionRule::TYPE_USER === $object->getUserType()) {
            $output->setUserId($object->getUserId());
        } elseif (RenditionRule::TYPE_GROUP === $object->getUserType()) {
            $output->setGroupId($object->getUserId());
        }

        if (RenditionRule::TYPE_COLLECTION === $object->getObjectType()) {
            $output->setCollectionId($object->getObjectId());
        } elseif (RenditionRule::TYPE_WORKSPACE === $object->getObjectType()) {
            $output->setWorkspaceId($object->getObjectId());
        }

        $output->setAllowed($object->getAllowed()->getValues());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return RenditionRuleOutput::class === $to && $data instanceof RenditionRule;
    }
}
