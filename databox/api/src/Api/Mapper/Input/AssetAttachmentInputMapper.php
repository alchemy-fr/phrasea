<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\AssetAttachmentInput;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AssetAttachmentInput::class)]
class AssetAttachmentInputMapper extends AbstractFileInputMapper implements InputMapperInterface
{
    /**
     * @param AssetAttachmentInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        /** @var AssetAttachment $object */
        $object = $target;
        $isNew = null === $object;
        if ($isNew) {
            $object = new AssetAttachment();
            $asset = $context['asset'] ?? $this->getEntity(Asset::class, $data->assetId);
            $object->setAsset($asset);
            $attachment = $this->getEntity(Asset::class, $data->attachmentId);

            if ($attachment->getWorkspaceId() !== $asset->getWorkspaceId()) {
                throw new \InvalidArgumentException(sprintf('Attachment "%s" does not belong to the same workspace as asset "%s"', $data->attachmentId, $data->assetId));
            }

            $object->setAttachment($attachment);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->priority) {
            $object->setPriority($data->priority);
        }

        return $object;
    }
}
