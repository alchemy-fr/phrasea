<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\AssetAttachmentInput;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AssetAttachmentInputTransformer extends AbstractFileInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return AssetAttachment::class === $resourceClass && $data instanceof AssetAttachmentInput;
    }

    /**
     * @param AssetAttachmentInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        /** @var AssetAttachment $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
        $isNew = null === $object;

        if ($isNew) {
            $object = new AssetAttachment();
            $asset = $context['asset'] ?? $this->getEntity(Asset::class, $data->assetId);
            $object->setAsset($asset);
        }

        $workspace = $object->getAsset()->getWorkspace();
        $file = $this->handleSource($data->sourceFile, $workspace)
            ?? $this->handleFromFile($data->sourceFileId, $workspace)
            ?? $this->handleUpload($data->multipart, $workspace);
        if (null !== $file) {
            $this->em->persist($file);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->priority) {
            $object->setPriority($data->priority);
        }

        $object->setFile($file);

        return $object;
    }
}
