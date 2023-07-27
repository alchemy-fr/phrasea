<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\CollectionInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CollectionInputTransformer extends AbstractInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return Collection::class === $resourceClass && $data instanceof CollectionInput;
    }

    /**
     * @param CollectionInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Collection();
        $object->setTitle($data->title);
        $this->transformPrivacy($data, $object);

        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        } elseif (null !== $data->parent) {
            $workspace = $data->parent->getWorkspace();
        }

        if ($isNew) {
            if (!$workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->key) {
                $collection = $this->em->getRepository(Collection::class)
                    ->findByKey($data->key, $workspace->getId());

                if ($collection) {
                    $isNew = false;
                    $object = $collection;
                }
            }
        }

        if ($isNew) {
            if ($workspace) {
                $object->setWorkspace($workspace);
            }

            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }
        }

        if (null !== $data->parent) {
            if (!$isNew) {
                throw new BadRequestHttpException(sprintf('Cannot change parent. Use POST /collections/%s/move', $object->getId()));
            }
            $object->setParent($data->parent);
        }

        if (null !== $data->key) {
            $object->setKey($data->key);
        }

        return $this->processOwnerId($object);
    }
}
