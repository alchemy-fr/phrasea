<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Output\RenditionRuleOutput;
use App\Entity\Core\RenditionRule;

class RenditionRuleOutputProcessor implements ProcessorInterface
{
    /**
     * @param RenditionRule $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new RenditionRuleOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        if (RenditionRule::TYPE_USER === $data->getUserType()) {
            $output->setUserId($data->getUserId());
        } elseif (RenditionRule::TYPE_GROUP === $data->getUserType()) {
            $output->setGroupId($data->getUserId());
        }

        if (RenditionRule::TYPE_COLLECTION === $data->getObjectType()) {
            $output->setCollectionId($data->getObjectId());
        } elseif (RenditionRule::TYPE_WORKSPACE === $data->getObjectType()) {
            $output->setWorkspaceId($data->getObjectId());
        }

        $output->setAllowed($data->getAllowed()->getValues());

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return RenditionRuleOutput::class === $to && $data instanceof RenditionRule;
    }
}
