<?php

declare(strict_types=1);

namespace App\Integration\Aws\Translate;

use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\AttributeInterface;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use App\Integration\AbstractIntegrationAction;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IfActionInterface;
use App\Integration\IntegrationConfig;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\AttributesResolver;
use Aws\Translate\TranslateClient;

class TranslateAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly ApiBudgetLimiter $apiBudgetLimiter,
        private readonly AttributesResolver $attributesResolver,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly BatchAttributeManager $batchAttributeManager,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);

        $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

        $defaultSourceLanguage = $config['defaultSourceLanguage'];
        $preferredSourceLanguages = $config['preferredSourceLanguages'];
        $translatedLanguages = $config['translatedLanguages'];

        $allEnabledLocales = $asset->getWorkspace()->getEnabledLocales();
        $lc = array_diff($allEnabledLocales, $preferredSourceLanguages);

        if (!empty($preferredSourceLanguages)) {
            $srcLocales = $preferredSourceLanguages;
            $srcLocales = array_merge($srcLocales, $lc, [AttributeInterface::NO_LOCALE]);
        } else {
            $srcLocales = array_merge($allEnabledLocales, [AttributeInterface::NO_LOCALE]);
        }

        if (empty($translatedLanguages)) {
            $translatedLanguages = $allEnabledLocales;
        }

        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);

        $attrDefs = $this->attributeDefinitionRepository->getAttributeDefinitions($asset->getWorkspaceId());

        $client = $this->createClient($config);

        $toTranslates = [];

        foreach ($attrDefs as $attrDef) {
            $text = '';
            $sourceLanguage = '';
            $destinationLanguages = [];

            if (!$attrDef->isTranslatable()) {
                continue;
            }

            if ($attrDef->isMultiple()) {
                foreach ($srcLocales as $locale) {
                    $attributesSources = $attributeIndex->getAttributes($attrDef->getId(), $locale);
                    $sourceLanguage = AttributeInterface::NO_LOCALE === $locale ? $defaultSourceLanguage : $locale;
                    if (!empty($attributesSources)) {
                        break;
                    }
                }

                if (empty($attributesSources)) {
                    continue;
                }

                foreach ($translatedLanguages as $destinationLanguage) {
                    $attributes = $attributeIndex->getAttributes($attrDef->getId(), $destinationLanguage);

                    if (empty($attributes)) {
                        $destinationLanguages[] = $destinationLanguage;
                    }
                }

                if (empty($destinationLanguages)) {
                    continue;
                }

                foreach ($attributesSources as $attribute) {
                    $text = $attribute->getValue();

                    if (empty($text)) {
                        continue;
                    }

                    $toTranslates[] = [
                        'text' => $text,
                        'sourceLanguage' => $sourceLanguage,
                        'destinationLanguages' => $destinationLanguages,
                        'definitionId' => $attrDef->getId(),
                        'action' => BatchAttributeManager::ACTION_ADD,
                    ];
                }
            } else {
                foreach ($srcLocales as $locale) {
                    $text = $attributeIndex->getAttribute($attrDef->getId(), $locale)?->getValue();
                    $sourceLanguage = AttributeInterface::NO_LOCALE === $locale ? $defaultSourceLanguage : $locale;
                    if (!empty($text)) {
                        break;
                    }
                }

                if (empty($text)) {
                    continue;
                }

                foreach ($translatedLanguages as $destinationLanguage) {
                    $t = $attributeIndex->getAttribute($attrDef->getId(), $destinationLanguage)?->getValue();

                    if (empty($t)) {
                        $destinationLanguages[] = $destinationLanguage;
                    }
                }

                if (empty($destinationLanguages)) {
                    continue;
                }

                $toTranslates[] = [
                    'text' => $text,
                    'sourceLanguage' => $sourceLanguage,
                    'destinationLanguages' => $destinationLanguages,
                    'definitionId' => $attrDef->getId(),
                    'action' => BatchAttributeManager::ACTION_SET,
                ];
            }
        }

        foreach ($toTranslates as $toTranslate) {
            $input = new AssetAttributeBatchUpdateInput();

            foreach ($toTranslate['destinationLanguages'] as $destinationLanguage) {
                $result = $client->translateText([
                    'SourceLanguageCode' => $toTranslate['sourceLanguage'],
                    'TargetLanguageCode' => $destinationLanguage,
                    'Text' => $toTranslate['text'],
                ]);

                $translatedText = $result->get('TranslatedText');
                $i = new AttributeActionInput();
                $i->definitionId = $toTranslate['definitionId'];
                $i->action = $toTranslate['action'];
                $i->origin = Attribute::ORIGIN_MACHINE;
                $i->originVendor = AwsTranslateIntegration::getName();
                $i->value = $translatedText;
                $i->locale = $destinationLanguage;

                $input->actions[] = $i;
            }

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
