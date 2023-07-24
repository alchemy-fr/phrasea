<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\RenditionRuleInput;
use App\Entity\Core\RenditionRule;

class RenditionRuleInputProcessor extends AbstractInputProcessor
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param RenditionRuleInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var RenditionRule $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new RenditionRule();

        $object->setUserId($data->userId ?? $data->groupId);
        $object->setUserType($data->groupId ? RenditionRule::TYPE_GROUP : RenditionRule::TYPE_USER);
        $object->setObjectId($data->collectionId ?? $data->workspaceId);
        $object->setObjectType($data->collectionId ? RenditionRule::TYPE_COLLECTION : RenditionRule::TYPE_WORKSPACE);
        $object->setAllowed($data->allowed);

        return $object;
    }
}
