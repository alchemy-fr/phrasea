<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Output\TagFilterRuleOutput;
use App\Entity\Core\TagFilterRule;

class TagFilterRuleOutputProcessor implements ProcessorInterface
{
    /**
     * @param TagFilterRule $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
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

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return TagFilterRuleOutput::class === $to && $data instanceof TagFilterRule;
    }
}
