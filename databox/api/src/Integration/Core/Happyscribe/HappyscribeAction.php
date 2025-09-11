<?php

declare(strict_types=1);

namespace App\Integration\Core\Happyscribe;

use Alchemy\StorageBundle\Util\FileUtil;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Storage\RenditionManager;
use Symfony\Component\Filesystem\Filesystem;
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
    ) {
    }

    public function doHandle(\Alchemy\Workflow\Executor\RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);
        $this->happyscribeToken = $config['api_key'];
        $organizationId = $config['organization_id'];
        $this->extension = $config['transcript_format'];

        if (!FileUtil::isVideoType($asset->getSource()->getType()) && !FileUtil::isAudioType($asset->getSource()->getType())) {
            return;
        }

        if (in_array(strtolower($$this->extension), ['srt', 'txt', 'json', 'vtt', 'docx', 'pdf', 'html'])) {
            $this->extension = strtolower($$this->extension);
        } else {
            throw new \InvalidArgumentException('Invalid transcript format, must be one of srt, vtt, txt, docx, pdf, json, html');
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

        $res = $this->happyscribeClient->request('PUT', $tmpUrl, [
            'body' => fopen($file->getPath(), 'r'),
        ]);

        if (200 !== $res->getStatusCode()) {
            throw new \RuntimeException('error when uploading file to signed url,response status : '.$res->getStatusCode());
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
                        'language' => 'fr-FR',
                        'organization_id' => $organizationId,
                        'tmp_url' => $tmpUrl,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            throw new \RuntimeException('Error when creating transcript : '.$e->getMessage());
        }

        if (200 !== $responseTranscription->getStatusCode()) {
            throw new \RuntimeException('error when creating transcript,response status : '.$responseTranscription->getStatusCode());
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

        $i = new AttributeActionInput();
        $i->name = $config['attribute'];
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendor = HappyscribeIntegration::getName();
        $i->value = $$this->exportTranscription($transcriptionId);
        //  $i->locale = $destinationLanguage;

        $input->actions[] = $i;

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
        $filesystem = new Filesystem();
        $subtitleTranscriptTemporaryFile = $filesystem->tempnam('/tmp', 'subtitle', $this->extension);

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

        $this->happyscribeClient->request('GET', $resCheckExportBody['download_link'], [
            'sink' => $subtitleTranscriptTemporaryFile,
        ]);

        $transcriptContent = file_get_contents($subtitleTranscriptTemporaryFile);

        return $transcriptContent;
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
            throw new \RuntimeException('error when translate, response status : '.$resTranslate->getStatusCode());
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
