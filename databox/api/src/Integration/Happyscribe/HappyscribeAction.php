<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe;

use Alchemy\StorageBundle\Util\FileUtil;
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
    private string $extension;
    private string $happyscribeToken;

    public function __construct(
        private readonly RenditionManager $renditionManager,
        private HttpClientInterface $happyscribeClient,
        private FileFetcher $fileFetcher,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributesResolver $attributesResolver,
        private MessageBusInterface $bus,
    ) {
    }

    public function doHandle(\Alchemy\Workflow\Executor\RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);
        $this->happyscribeToken = $config['apiKey'];
        $this->extension = $config['transcriptFormat'];
        $organizationId = $config['organizationId'];
        $allEnabledLocales = $asset->getWorkspace()->getEnabledLocales();
        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);

        if (!FileUtil::isVideoType($asset->getSource()->getType()) && !FileUtil::isAudioType($asset->getSource()->getType())) {
            return;
        }

        if (in_array(strtolower($this->extension), ['srt', 'txt', 'json', 'vtt', 'docx', 'pdf', 'html'])) {
            $this->extension = strtolower($this->extension);
        } else {
            throw new \InvalidArgumentException('Invalid transcript format, must be one of srt, vtt, txt, docx, pdf, json, html');
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
                'Authorization' => 'Bearer '.$this->happyscribeToken,
            ],
        ]);

        if (200 !== $responseUpload->getStatusCode()) {
            throw new \RuntimeException('Error when getting upload url from Happyscribe, response status : '.$responseUpload->getStatusCode());
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
            throw new \RuntimeException('error when uploading file to signed url, response status : '.$res->getStatusCode().', message : '.$res->getContent(false));
        }

        $srcLanguageAttrDef = $this->attributeDefinitionRepository
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $config['sourceLanguageAttribute'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $config['sourceLanguageAttribute'], $asset->getWorkspaceId()));

        $sourceLanguage = $attributeIndex->getAttribute($srcLanguageAttrDef->getId(), AttributeInterface::NO_LOCALE)?->getValue() ?? $config['defaultSourceLanguage'];
        $sourceLanguage = trim($sourceLanguage);

        if (!in_array(strlen($sourceLanguage), [2, 5])) {
            $t = explode('-', $sourceLanguage);
            $sourceLanguage = $t[0];

            if (2 != strlen($sourceLanguage)) {
                throw new \InvalidArgumentException('Source language code must be a 2-letter or 4-letter code, eg: en, fr-FR, ...');
            }
        }

        try {
            $responseTranscription = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
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
            throw new \RuntimeException('Error when creating transcript : '.$e->getMessage());
        }

        if (200 !== $responseTranscription->getStatusCode()) {
            throw new \RuntimeException('error when creating transcript,response status : '.$responseTranscription->getStatusCode().' message : '.$responseTranscription->getContent(false));
        }

        $responseTranscriptionBody = $responseTranscription->toArray();
        $transcriptionId = $responseTranscriptionBody['id'];

        $this->happyscribeToken =
        $this->extension = $config['transcriptFormat'];
        $organizationId = $config['organizationId'];

        $options['apiKey'] = $config['apiKey'];
        $options['transcriptFormat'] = $config['transcriptFormat'];
        $options['attribute'] = $config['attribute'];
        $options['sourceLanguageAttribute'] = $config['sourceLanguageAttribute'];
        $options['defaultSourceLanguage'] = $config['defaultSourceLanguage'];
        $options['assetId'] = $asset->getId();
        $options['workspaceId'] = $asset->getWorkspaceId();
        $options['sourceLanguage'] = $sourceLanguage;
        $options['attributeId'] = $attrDef->getId();
        $options['isTranslatableAttribute'] = $attrDef->isTranslatable();
        $options['isMultipleAttribute'] = $attrDef->isMultiple();

        $this->bus->dispatch(new TranscriptionHappyscribeMessage($transcriptionId, json_encode($options)), [new DelayStamp(60 * 1000)]);
    }

    private function getRenditionFile(string $assetId, string $renditionName): File
    {
        $rendition = $this->renditionManager->getAssetRenditionByName($assetId, $renditionName)
            ?? throw new \InvalidArgumentException(sprintf('Rendition "%s" does not exist for asset "%s"', $renditionName, $assetId));

        return $rendition->getFile();
    }
}
