<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use App\Api\Model\Input\RenditionRuleInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Core\RenditionRule;

class RenditionRuleInputTransformer extends AbstractInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return RenditionRule::class === $resourceClass && $data instanceof RenditionRuleInput;
    }

    /**
     * @param RenditionRuleInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        /** @var RenditionRule $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new RenditionRule();

        $object->setUserId($data->userId ?? $data->groupId);
        $object->setUserType($data->groupId ? RenditionRule::TYPE_GROUP : RenditionRule::TYPE_USER);
        $object->setObjectId($data->collectionId ?? $data->workspaceId);
        $object->setObjectType($data->collectionId ? RenditionRule::TYPE_COLLECTION : RenditionRule::TYPE_WORKSPACE);
        $object->setAllowed($data->allowed);

        return $object;
    }
}
