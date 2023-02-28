<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CollectionInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    /**
     * @param CollectionInput $data
     */
    public function transform($data, string $to, array $context = [])
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

        return $this->transformOwnerId($object, $to, $context);
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Collection) {
            return false;
        }

        return Collection::class === $to && CollectionInput::class === ($context['input']['class'] ?? null);
    }
}
