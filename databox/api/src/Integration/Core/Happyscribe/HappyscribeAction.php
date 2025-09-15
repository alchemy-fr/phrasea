<?php

declare(strict_types=1);

namespace App\Integration\Core\Happyscribe;

use Alchemy\StorageBundle\Util\FileUtil;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\FileFetcher;
use App\Attribute\AttributeInterface;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Storage\RenditionManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HappyscribeAction extends AbstractIntegrationAction implements IfActionInterface
{
    private string $extension;
    private string $happyscribeToken;

    public function __construct(
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly RenditionManager $renditionManager,
        private HttpClientInterface $happyscribeClient,
        private FileFetcher $fileFetcher,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributesResolver $attributesResolver,
    ) {
    }

    public function doHandle(\Alchemy\Workflow\Executor\RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);
        $this->happyscribeToken = $config['api_key'];
        $this->extension = $config['transcript_format'];
        $organizationId = $config['organization_id'];
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

        $t = explode('-', $sourceLanguage);
        $sourceLanguage = $t[0];

        if (2 != strlen($sourceLanguage)) {
            throw new \InvalidArgumentException('Source language code must be a 2-letter code, eg: en, fr, ...');
        }

        // create a transcription

        try {
            $responseTranscription = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
                ],
                'json' => [
                    'transcription' => [
                        'name' => $asset->getTitle(),
                        'is_subtitle' => true,
                        'language' => strtolower($sourceLanguage),
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

        // check transcription status
        $failureTranscriptMessage = '';

        do {
            // first wait 5 second before check transcript status
            sleep(5);
            $resCheckTranscript = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/transcriptions/'.$transcriptionId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
                ],
            ]);

            if (200 !== $resCheckTranscript->getStatusCode()) {
                throw new \RuntimeException('error when checking transcript,response status : '.$resCheckTranscript->getStatusCode());
            }

            $resCheckTranscriptBody = $resCheckTranscript->toArray();

            $transcriptStatus = $resCheckTranscriptBody['state'];
            if (isset($resCheckTranscriptBody['failureMessage'])) {
                $failureTranscriptMessage = $resCheckTranscriptBody['failureMessage'];
            }

        } while (!in_array($transcriptStatus, ['automatic_done', 'locked', 'failed']));

        if ('automatic_done' != $transcriptStatus) {
            throw new \RuntimeException('transcription failed, status : '.$transcriptStatus.', message : '.$failureTranscriptMessage);
        }

        $input = new AssetAttributeBatchUpdateInput();

        foreach ($allEnabledLocales as $locale) {
            if (!$attrDef->isTranslatable() && AttributeInterface::NO_LOCALE !== $locale) {
                continue;
            }

            if ($attrDef->isMultiple()) {
                throw new \InvalidArgumentException(sprintf('Attribute "%s" must be mono-valued', $attrDef->getId()));
            }

            if (AttributeInterface::NO_LOCALE !== $locale && 1 !== preg_match('/'.$locale.'/', $sourceLanguage)) {
                $transcriptionContent = $this->translate($transcriptionId, $locale);
            } else {
                $transcriptionContent = $this->exportTranscription($transcriptionId);
            }

            $i = new AttributeActionInput();
            $i->definitionId = $attrDef->getId();
            $i->origin = Attribute::ORIGIN_MACHINE;
            $i->originVendor = HappyscribeIntegration::getName();
            $i->value = $transcriptionContent;
            $i->locale = $locale;

            $input->actions[] = $i;

        }

        try {
            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$asset->getId()],
                $input,
                null
            );
        } catch (BadRequestHttpException $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    private function exportTranscription($transcriptionId)
    {
        $resExport = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/exports', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->happyscribeToken,
            ],
            'json' => [
                'export' => [
                    'format' => $this->extension,
                    'transcription_ids' => [
                        $transcriptionId,
                    ],
                ],
            ],
        ]);

        if (200 !== $resExport->getStatusCode()) {
            throw new \RuntimeException('error when creating transcript export, response status : '.$resExport->getStatusCode());

        }

        $resExportBody = $resExport->toArray();

        $exportId = $resExportBody['id'];
        $failureExportMessage = '';

        // retrieve transcript export when ready
        do {
            sleep(3);
            $resCheckExport = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/exports/'.$exportId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
                ],
            ]);

            if (200 !== $resCheckExport->getStatusCode()) {
                throw new \RuntimeException('error when checking transcript export, response status : '.$resCheckExport->getStatusCode());
            }

            $resCheckExportBody = $resCheckExport->toArray();

            $exportStatus = $resCheckExportBody['state'];
            if (isset($resCheckExportBody['failureMessage'])) {
                $failureExportMessage = $resCheckExportBody['failureMessage'];
            }

        } while (!in_array($exportStatus, ['ready', 'expired', 'failed']));

        if ('ready' != $exportStatus) {
            throw new \RuntimeException('exporting transcript failed, status : '.$exportStatus.', message : '.$failureExportMessage);
        }

        $res = $this->happyscribeClient->request('GET', $resCheckExportBody['download_link']);

        return $res->getContent();
    }

    private function translate($sourceTranscriptionId, $targetLanguage)
    {
        // translate
        try {
            $resTranslate = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/task/transcription_translation', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
                ],
                'json' => [
                    'source_transcription_id' => $sourceTranscriptionId,
                    'target_language' => strtolower($targetLanguage),
                ],
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('error when translate : '.$e->getMessage());
        }

        if (200 !== $resTranslate->getStatusCode()) {
            throw new \RuntimeException('error when translate, response status : '.$resTranslate->getStatusCode().'target language'.$targetLanguage);
        }

        $resTranslateBody = $resTranslate->toArray();

        if ('failed' == $resTranslateBody['state']) {
            throw new \RuntimeException('failed when translate, : '.$resTranslateBody['failureReason']);
        }

        $translateId = $resTranslateBody['id'];

        // check translation
        $failureTranslateMessage = '';
        $translatedTranscriptionId = '';

        do {
            sleep(5);

            $resCheckTranslate = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/task/transcription_translation/'.$translateId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
                ],
            ]);

            if (200 !== $resCheckTranslate->getStatusCode()) {
                throw new \RuntimeException('error when checking translation task ,response status : '.$resCheckTranslate->getStatusCode());
            }

            $resCheckTranslateBody = $resCheckTranslate->toArray();

            $checkTranslateStatus = $resCheckTranslateBody['state'];
            if (isset($resCheckTranslateBody['failureReason'])) {
                $failureTranslateMessage = $resCheckTranslateBody['failureReason'];
            }

            if ('done' == $checkTranslateStatus) {
                $translatedTranscriptionId = $resCheckTranslateBody['translatedTranscriptionId'];
            }

        } while (!in_array($checkTranslateStatus, ['done', 'failed']));

        if ('done' != $checkTranslateStatus) {
            throw new \RuntimeException('error when translate : '.$failureTranslateMessage);
        }

        // export the translation now

        return $this->exportTranscription($translatedTranscriptionId);
    }

    private function getRenditionFile(string $assetId, string $renditionName): File
    {
        $rendition = $this->renditionManager->getAssetRenditionByName($assetId, $renditionName)
            ?? throw new \InvalidArgumentException(sprintf('Rendition "%s" does not exist for asset "%s"', $renditionName, $assetId));

        return $rendition->getFile();
    }
}
