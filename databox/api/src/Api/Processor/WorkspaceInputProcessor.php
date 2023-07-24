<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\WorkspaceInput;
use App\Entity\Core\Workspace;

class WorkspaceInputProcessor extends AbstractInputProcessor
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param WorkspaceInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Workspace $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Workspace();
        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->slug) {
            $object->setSlug($data->slug);
        }
        if (null !== $data->enabledLocales) {
            $object->setEnabledLocales(array_values($data->enabledLocales));
        }
        if (null !== $data->localeFallbacks) {
            $object->setLocaleFallbacks(array_values($data->localeFallbacks));
        }

        if ($isNew) {
            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }
        }

        return $this->processOwnerId($object);
    }
}
