<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\AssetImportOutput;
use App\Entity\Core\AssetImport;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AssetImportOutput::class)]
class AssetImportOutputMapper implements OutputMapperInterface
{
    use UserOutputTrait;

    public function supports(object $data): bool
    {
        return $data instanceof AssetImport;
    }

    /**
     * @param AssetImport $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AssetImportOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());

        $output->workspaceId = $data->getWorkspaceId();
        if (null !== $collection = $data->getCollection()) {
            $output->collectionId = $collection->getId();
            $output->collectionTitle = $collection->getName();
        }
        if (null !== $integration = $data->getIntegration()) {
            $output->integrationId = $integration->getId();
            $output->integrationTitle = $integration->getName() ?: $integration->getIntegration();
        }
        $output->owner = $this->transformUser($data->getOwnerId());
        $output->format = $data->getFormat();
        $output->privacy = $data->getPrivacy();
        $output->originalName = $data->getOriginalName();
        $output->status = $data->getStatus()->getLabel();
        $output->total = $data->getTotal();
        $output->created = $data->getCreated();
        $output->skipped = $data->getSkipped();
        $output->failed = $data->getFailed();
        $output->error = $data->getError();
        $output->endedAt = $data->getEndedAt();

        return $output;
    }
}
