<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\WorkspaceInput;
use App\Entity\Core\Workspace;

class WorkspaceInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    /**
     * @param WorkspaceInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Workspace $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Workspace();
        $object->setName($data->name);

        if ($isNew) {
            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }
        }

        return $this->transformOwnerId($object, $to, $context);
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Workspace) {
            return false;
        }

        return Workspace::class === $to && WorkspaceInput::class === ($context['input']['class'] ?? null);
    }
}