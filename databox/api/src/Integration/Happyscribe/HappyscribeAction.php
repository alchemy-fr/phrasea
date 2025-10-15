<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\FileFetcher;
use App\Attribute\AttributeInterface;
use App\Entity\Core\File;
use App\Integration\AbstractIntegrationAction;
use App\Integration\Happyscribe\Consumer\TranscriptionHappyscribeMessage;
use App\Integration\IfActionInterface;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Storage\RenditionManager;
use Symfony\Component\Messenger\MessageBusInterface;

use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HappyscribeAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly RenditionManager $renditionManager,
        private readonly HttpClientInterface $happyscribeClient,
        private readonly FileFetcher $fileFetcher,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributesResolver $attributesResolver,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);
        $integrationId = $context->getInputs()['integrationId'];
        $extension = $config['transcriptFormat'];
        $organizationId = $config['organizationId'];
        $allEnabledLocales = $asset->getWorkspace()->getEnabledLocales();
        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);

        if (!FileUtil::isVideoType($asset->getSource()->getType()) && !FileUtil::isAudioType($asset->getSource()->getType())) {
            return;
        }

        if (in_array(strtolower($extension), HappyscribeIntegration::ALLOWED_EXTENSIONS, true)) {
            $extension = strtolower($extension);
        } else {
            throw new \InvalidArgumentException('Invalid transcript format, must be one of '.implode(', ', HappyscribeIntegration::ALLOWED_EXTENSIONS));
        }

        $attrDef = $this->attributeDefinitionRepository
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $config['attribute'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $config['attribute'], $asset->getWorkspaceId()));

        $toTranscript = false;
        foreach ($allEnabledLocales as $locale) {
            $attribute = $attributeIndex->getAttribute($attrDef->getId(), $locale);
            if (empty($attribute)) {
                $toTranscript = true;
                break;
            }
        }

        if (!$toTranscript) {
            return;
        }

        $fileName = $asset->getSource()->getFilename();

        $file = !empty($config['rendition']) ? $this->getRenditionFile($asset->getId(), $config['rendition']) : $asset->getSource();

        $responseUpload = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/uploads/new?filename='.$fileName, [
            'headers' => [
                'Authorization' => 'Bearer '.$config['apiKey'],
            ],
        ]);

        if (200 !== $responseUpload->getStatusCode()) {
            throw new \RuntimeException('Error when getting upload url from Happyscribe, response status: '.$responseUpload->getStatusCode());
        }

        $responseUploadBody = $responseUpload->toArray();

        $tmpUrl = $responseUploadBody['signedUrl'];

        $fetchedFilePath = $this->fileFetcher->getFile($file);

        $res = $this->happyscribeClient->request('PUT', $tmpUrl, [
            'headers' => [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => filesize($fetchedFilePath),
            ],
            'body' => fopen($fetchedFilePath, 'r'),
        ]);

        if (200 !== $res->getStatusCode()) {
            throw new \RuntimeException('Error when uploading file to signed url, response status: '.$res->getStatusCode().', message: '.$res->getContent(false));
        }

        $srcLanguageAttrDef = $this->attributeDefinitionRepository
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $config['sourceLanguageAttribute'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $config['sourceLanguageAttribute'], $asset->getWorkspaceId()));

        $sourceLanguage = $attributeIndex->getAttribute($srcLanguageAttrDef->getId(), AttributeInterface::NO_LOCALE)?->getValue() ?? $config['defaultSourceLanguage'];
        $sourceLanguage = trim($sourceLanguage);

        if (!in_array(strlen($sourceLanguage), [2, 5])) {
            $t = explode('-', $sourceLanguage, 2);
            $sourceLanguage = $t[0];

            if (2 !== strlen($sourceLanguage)) {
                throw new \InvalidArgumentException('Source language code must be a 2-letter or 4-letter code, eg: en, fr-FR');
            }
        }

        try {
            $responseTranscription = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$config['apiKey'],
                ],
                'json' => [
                    'transcription' => [
                        'name' => $asset->getTitle(),
                        'is_subtitle' => true,
                        'language' => $sourceLanguage,
                        'organization_id' => $organizationId,
                        'tmp_url' => $tmpUrl,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            throw new \RuntimeException('Error when creating transcript: '.$e->getMessage(), previous: $e);
        }

        if (200 !== $responseTranscription->getStatusCode()) {
            throw new \RuntimeException('Error when creating transcript,response status: '.$responseTranscription->getStatusCode().' message: '.$responseTranscription->getContent(false));
        }

        $responseTranscriptionBody = $responseTranscription->toArray();
        $transcriptionId = $responseTranscriptionBody['id'];

        $this->bus->dispatch(new TranscriptionHappyscribeMessage($transcriptionId, $integrationId, $asset->getId(), $sourceLanguage), [new DelayStamp(30 * 1000)]);
    }

    private function getRenditionFile(string $assetId, string $renditionName): File
    {
        $rendition = $this->renditionManager->getAssetRenditionByName($assetId, $renditionName)
            ?? throw new \InvalidArgumentException(sprintf('Rendition "%s" does not exist for asset "%s"', $renditionName, $assetId));

        return $rendition->getFile();
    }
}
