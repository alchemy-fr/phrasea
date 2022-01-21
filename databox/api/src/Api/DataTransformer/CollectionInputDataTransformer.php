<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CollectionInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

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
                $asset = $this->em->getRepository(Collection::class)
                    ->findOneBy([
                        'key' => $data->key,
                        'workspace' => $workspace->getId(),
                    ]);

                if ($asset) {
                    $isNew = false;
                    $object = $asset;
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
