<?php

declare(strict_types=1);

namespace App\Integration\Clarifai;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\AssetOperationIntegrationInterface;

// TODO remove abstract
abstract class ClarifaiIntegration implements AssetOperationIntegrationInterface
{
    private ClarifaiClient $client;
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(ClarifaiClient $client, BatchAttributeManager $batchAttributeManager)
    {
        $this->client = $client;
        $this->batchAttributeManager = $batchAttributeManager;
    }

    public function handleAsset(WorkspaceIntegration $workspaceIntegration, Asset $asset): void
    {
        $concepts = $this->client->getImageConcepts($asset->getFile());

        $input = new AssetAttributeBatchUpdateInput();

        foreach ($concepts as $concept => $confidence) {
            $i = new AttributeActionInput();
            $i->name = 'keywords';
            $i->confidence = $confidence;
            $i->value = $concept;
            $input->actions[] = $i;
        }

        $this->batchAttributeManager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input);
    }

    public static function getName(): string
    {
        return 'Clarify';
    }
}
