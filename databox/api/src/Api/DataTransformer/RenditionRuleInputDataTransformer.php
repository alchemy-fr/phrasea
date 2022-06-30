<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\RenditionRuleInput;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionRule;
use Doctrine\ORM\EntityManagerInterface;

class RenditionRuleInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param RenditionRuleInput $data
     */
    public function transform($data, string $to, array $context = [])
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

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return RenditionRule::class === $to && RenditionRuleInput::class === ($context['input']['class'] ?? null);
    }
}
