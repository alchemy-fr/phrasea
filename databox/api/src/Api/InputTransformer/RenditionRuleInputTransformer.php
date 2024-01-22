<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\RenditionRuleInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Core\RenditionRule;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

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
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;

        $objectId = $data->collectionId ?? $data->workspaceId;
        $userId = $data->userId ?? $data->groupId;
        $userType = $data->groupId ? RenditionRule::TYPE_GROUP : RenditionRule::TYPE_USER;
        $objectType = $data->collectionId ? RenditionRule::TYPE_COLLECTION : RenditionRule::TYPE_WORKSPACE;

        $isNew = null === $object;
        if ($isNew) {
            $object = $this->em->getRepository(RenditionRule::class)
                ->findOneBy([
                    'objectId' => $objectId,
                    'userId' => $userId,
                    'userType' => $userType,
                    'objectType' => $objectType,
                ]);

            $object ??= new RenditionRule();

            $allowedCollection = $object->getAllowed();
            foreach ($data->allowed as $allowed) {
                $allowedCollection->add($allowed);
            }
        }

        $object->setUserId($userId);
        $object->setUserType($userType);
        $object->setObjectId($objectId);
        $object->setObjectType($objectType);
        if (!$isNew) {
            $object->setAllowed($data->allowed);
        }

        return $object;
    }
}
