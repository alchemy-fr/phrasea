<?php

declare(strict_types=1);

namespace App\Integration\Aws\Translate;

use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\Attribute\AttributesResolver;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeManager;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use App\Integration\AbstractIntegrationAction;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IfActionInterface;
use App\Integration\IntegrationConfig;
use Aws\Translate\TranslateClient;

class TranslateAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly ApiBudgetLimiter $apiBudgetLimiter,
        private readonly AttributesResolver $attributesResolver,
        private readonly AttributeManager $attributeManager,
        private BatchAttributeManager $batchAttributeManager,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);

        $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

        $sourceLangage = $config['source_lng'];
        $destinationLanguage = $config['destination_lng'];

        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);

        $attrDefs = $this->attributeManager->getAttributeDefinitions($asset->getWorkspaceId());

        foreach ($attrDefs as $attrDef) {
            if (!$attrDef->isTranslatable()) {
                continue;
            }

            $text = $attributeIndex->getAttribute($attrDef->getId(), AttributeInterface::NO_LOCALE)?->getValue();
            if (empty($text)) {
                continue;
            }
            $client = $this->createClient($config);

            $result = $client->translateText([
                'Settings' => [
                    'Brevity' => 'ON',
                    'Formality' => 'FORMAL',
                    'Profanity' => 'MASK',
                ],
                'SourceLanguageCode' => $sourceLangage,
                'TargetLanguageCode' => $destinationLanguage,

                'Text' => $text,
            ]);

            $translatedText = $result->get('TranslatedText');

            $input = new AssetAttributeBatchUpdateInput();
            $i = new AttributeActionInput();
            $i->definitionId = $attrDef->getId();
            $i->action = BatchAttributeManager::ACTION_SET;
            $i->origin = Attribute::ORIGIN_MACHINE;
            $i->originVendor = AwsTranslateIntegration::getName();
            $i->value = $translatedText;
            $i->locale = $destinationLanguage;
            $input->actions[] = $i;

            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$asset->getId()],
                $input,
                null
            );

        }
    }

    private function createClient(IntegrationConfig $options): TranslateClient
    {
        return new TranslateClient([
            'region' => $options['region'],
            'credentials' => [
                'key' => $options['accessKeyId'],
                'secret' => $options['accessKeySecret'],
            ],
            'version' => 'latest',
        ]);
    }
}
