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
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClarifaiConceptsIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface
{
    private BatchAttributeManager $batchAttributeManager;
    private ClarifaiClient $client;

    public function __construct(BatchAttributeManager $batchAttributeManager, ClarifaiClient $client)
    {
        $this->batchAttributeManager = $batchAttributeManager;
        $this->client = $client;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['apiKey']);
        $resolver->setAllowedTypes('apiKey', ['string']);
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        if (!$asset->getFile() || !FileUtil::isImageType($asset->getFile()->getType())) {
            return;
        }

        $concepts = $this->client->getImageConcepts($asset->getFile(), $options['apiKey']);
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

        $this->batchAttributeManager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input);
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
