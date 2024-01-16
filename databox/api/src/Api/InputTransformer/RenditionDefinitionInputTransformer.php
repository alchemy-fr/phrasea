<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\RenditionDefinitionInput;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class RenditionDefinitionInputTransformer extends AbstractInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return RenditionDefinition::class === $resourceClass && $data instanceof RenditionDefinitionInput;
    }

    /**
     * @param RenditionDefinitionInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var RenditionDefinition $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new RenditionDefinition();

        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        }

        if ($isNew) {
            if (!$workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->key) {
                $rendDef = $this->em->getRepository(RenditionDefinition::class)
                    ->findOneBy([
                        'key' => $data->key,
                        'workspace' => $workspace->getId(),
                    ]);

                if ($rendDef) {
                    $isNew = false;
                    $object = $rendDef;
                }
            }
        }

        if ($isNew) {
            $object->setWorkspace($workspace);
            $object->setKey($data->key);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->class) {
            $object->setClass($data->class);
        }

        if (null !== $data->download) {
            $object->setDownload($data->download);
        }
        if (null !== $data->pickSourceFile) {
            $object->setPickSourceFile($data->pickSourceFile);
        }
        if (null !== $data->useAsOriginal) {
            $object->setUseAsOriginal($data->useAsOriginal);
        }
        if (null !== $data->useAsPreview) {
            $object->setUseAsPreview($data->useAsPreview);
        }
        if (null !== $data->useAsThumbnail) {
            $object->setUseAsThumbnail($data->useAsThumbnail);
        }
        if (null !== $data->useAsThumbnailActive) {
            $object->setUseAsThumbnailActive($data->useAsThumbnailActive);
        }
        if (null !== $data->definition) {
            $object->setDefinition($data->definition);
        }
        if (null !== $data->priority) {
            $object->setPriority($data->priority);
        }
        if (null !== $data->labels) {
            $object->setLabels($data->labels);
        }

        return $object;
    }
}
