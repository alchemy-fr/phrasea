<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\AttributeListInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\AttributeList\AttributeList;
use App\Entity\AttributeList\AttributeListItem;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AttributeListInputTransformer extends AbstractFileInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return AttributeList::class === $resourceClass && $data instanceof AttributeListInput;
    }

    /**
     * @param AttributeListInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !($context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null);
        /** @var AttributeList $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AttributeList();

        if (null !== $data->public) {
            $object->setPublic($data->public);
        }

        if (null !== $data->title) {
            $object->setTitle($data->title);
        }

        if (null !== $data->description) {
            $object->setDescription($data->description);
        }

        if (null !== $data->items) {
            $definitions = $object->getItems();
            if (!$isNew) {
                $definitions->clear();
                $repository = $this->em->getRepository(AttributeListItem::class);
                $repository->createQueryBuilder('a')
                    ->delete()
                    ->where('a.list = :list')
                    ->setParameter('list', $object)
                    ->getQuery()
                    ->execute();
            }

            foreach ($data->items as $definition) {
                $defId = is_string($definition) ? $definition : $definition['id'] ?? null;
                if ($defId) {
                    $newDefinition = new AttributeListItem();
                    $newDefinition->setList($object);
                    $newDefinition->setPosition($definition['position'] ?? 0);
                    if (str_starts_with($defId, '@')) {
                        $newDefinition->setBuiltIn($defId);
                    } else {
                        $newDefinition->setDefinition($this->em->getReference(AttributeDefinition::class, $defId));
                    }
                    $this->em->persist($newDefinition);
                    $definitions->add($newDefinition);
                }
            }
        }

        return $this->processOwnerId($object);
    }
}
