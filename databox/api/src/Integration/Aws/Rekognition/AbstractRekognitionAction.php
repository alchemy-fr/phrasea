<?php

declare(strict_types=1);

namespace App\Integration\Aws\Rekognition;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Util\FileUtil;

abstract class AbstractRekognitionAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly RekognitionAnalyzer $analyzer,
        protected readonly BatchAttributeManager $batchAttributeManager,
    )
    {
    }

    public function handle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);
        $file = $asset->getSource();
        $category = $this->getCategory();
        $result = $this->analyzer->analyze($file, $category, $config);

        $this->handleResult($asset, $result, $config);
    }

    abstract protected function getCategory(): string;
    abstract protected function handleResult(Asset $asset, array $result, array $config): void;

    public function shouldRun(JobContext $context): bool
    {
        $asset = $this->getAsset($context);
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isImageType($asset->getSource()->getType());
    }

    protected function saveTextsToAttributes(Asset $asset, array $texts, array $attributes): void
    {
        foreach ($attributes as $attrConfig) {
            $attrDef = $this->batchAttributeManager->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $attrConfig['name']);
            $threshold = $attrConfig['threshold'] ?? null;
            if (!$attrDef->isMultiple()) {
                throw new \InvalidArgumentException(sprintf('Attribute "%s" must be multi-valued', $attrDef->getId()));
            }

            $input = new AssetAttributeBatchUpdateInput();
            $i = new AttributeActionInput();
            $i->definitionId = $attrDef->getId();
            $i->action = BatchAttributeManager::ACTION_DELETE;
            $i->origin = Attribute::ORIGIN_MACHINE;
            $i->originVendor = AwsRekognitionIntegration::getName();
            $input->actions[] = $i;

            foreach ($texts as $text) {
                if (null === $threshold || $threshold < $text['confidence']) {
                    $i = new AttributeActionInput();
                    $i->action = BatchAttributeManager::ACTION_ADD;
                    $i->originVendor = AwsRekognitionIntegration::getName();
                    $i->origin = Attribute::ORIGIN_MACHINE;
                    $i->definitionId = $attrDef->getId();
                    $i->confidence = $text['confidence'];
                    $i->value = $text['value'];
                    $input->actions[] = $i;
                }
            }

            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$asset->getId()],
                $input,
                null
            );
        }
    }
}
