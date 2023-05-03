<?php

declare(strict_types=1);

namespace App\Integration\Clarifai;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegration;
use App\Integration\AssetOperationIntegrationInterface;
use App\Util\FileUtil;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class ClarifaiConceptsIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface
{
    public function __construct(private readonly BatchAttributeManager $batchAttributeManager, private readonly ClarifaiClient $client)
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('apiKey')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public function handleAsset(Asset $asset, array $config): void
    {
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

    public function supportsAsset(Asset $asset, array $config): bool
    {
        return $asset->getSource() && FileUtil::isImageType($asset->getSource()->getType());
    }

    public static function getName(): string
    {
        return 'clarify.concepts';
    }

    public static function getTitle(): string
    {
        return 'Clarify concepts';
    }
}
