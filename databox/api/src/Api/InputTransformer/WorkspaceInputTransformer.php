<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\WorkspaceInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Core\Workspace;

class WorkspaceInputTransformer extends AbstractInputTransformer
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param WorkspaceInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object
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

    public function supports(string $resourceClass, object $data): bool
    {
        return $resourceClass === Workspace::class && $data instanceof WorkspaceInput;
    }
}
