<?php

declare(strict_types=1);

namespace App\Integration\Clarifai;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Util\FileUtil;

class ClarifaiConceptsAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly ClarifaiClient $client,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $concepts = $this->client->getImageConcepts($asset->getSource(), $config['apiKey']);
        if (empty($concepts)) {
            return;
        }

        $input = new AssetAttributeBatchUpdateInput();
        foreach ($concepts as $concept => $confidence) {
            $i = new AttributeActionInput();
            $i->name = 'keywords';
            $i->confidence = $confidence;
            $i->value = $concept;
            $input->actions[] = $i;
        }

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $input,
            null
        );
    }

    public function shouldRun(JobContext $context): bool
    {
        $asset = $this->getAsset($context);
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isImageType($asset->getSource()->getType());
    }
}
