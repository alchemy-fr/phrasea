<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\TagFilterRuleOutput;
use App\Entity\Core\TagFilterRule;

class TagFilterRuleOutputProcessor implements OutputTransformerInterface
{
    public function supports(string $outputClass, object $data): bool
    {
        return TagFilterRuleOutput::class === $outputClass && $data instanceof TagFilterRule;
    }

    /**
     * @param TagFilterRule $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new TagFilterRuleOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        if (TagFilterRule::TYPE_USER === $data->getUserType()) {
            $output->setUserId($data->getUserId());
        } elseif (TagFilterRule::TYPE_GROUP === $data->getUserType()) {
            $output->setGroupId($data->getUserId());
        }

        if (TagFilterRule::TYPE_COLLECTION === $data->getObjectType()) {
            $output->setCollectionId($data->getObjectId());
        } elseif (TagFilterRule::TYPE_WORKSPACE === $data->getObjectType()) {
            $output->setWorkspaceId($data->getObjectId());
        }

        $output->setInclude($data->getInclude()->getValues());
        $output->setExclude($data->getExclude()->getValues());

        return $output;
    }
}
