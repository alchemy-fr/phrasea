<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Asset;
use App\Integration\IntegrationManager;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\AttributesResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class TranscriptionHappyscribeMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private HttpClientInterface $happyscribeClient,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private AttributesResolver $attributesResolver,
    ) {
    }

    public function __invoke(TranscriptionHappyscribeMessage $message): void
    {
        $transcriptionId = $message->getTranscriptionId();
        $integrationId = $message->getIntegrationId();
        $assetId = $message->getAssetId();
        $sourceLanguage = $message->getSourceLanguage();

        $integration = $this->integrationManager->loadIntegration($integrationId) ?? throw new \InvalidArgumentException('Integration not found: '.$integrationId);

        $integrationConfig = $this->integrationManager->getIntegrationConfiguration($integration);

        $asset = $this->em->find(Asset::class, $assetId) ?? throw new \InvalidArgumentException('Asset not found: '.$assetId);
        $allEnabledLocales = $asset->getWorkspace()->getEnabledLocales();
        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);

        $failureTranscriptMessage = '';

        $resCheckTranscript = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/transcriptions/'.$transcriptionId, [
            'headers' => [
                'Authorization' => 'Bearer '.$integrationConfig['apiKey'],
            ],
        ]);

        if (200 !== $resCheckTranscript->getStatusCode()) {
            throw new \RuntimeException('Error when checking transcript, response status: '.$resCheckTranscript->getStatusCode());
        }

        $resCheckTranscriptBody = $resCheckTranscript->toArray();

        $transcriptStatus = $resCheckTranscriptBody['state'];
        if (isset($resCheckTranscriptBody['failureMessage'])) {
            $failureTranscriptMessage = $resCheckTranscriptBody['failureMessage'];
        }

        if (!in_array($transcriptStatus, ['automatic_done', 'locked', 'failed'], true)) {
            $retryNumber = $message->getRetry();
            $delays = [30, 80, 150, 200];
            $delay = $delays[$retryNumber] ?? 240;

            $delay = $delay * 1000;

            $this->bus->dispatch(new TranscriptionHappyscribeMessage($transcriptionId, $integrationId, $assetId, $sourceLanguage, $retryNumber + 1), [new DelayStamp($delay)]);

            return;
        }

        if ('automatic_done' !== $transcriptStatus) {
            throw new \RuntimeException('Transcription failed, status: '.$transcriptStatus.', message: '.$failureTranscriptMessage);
        }

        $attrDef = $this->attributeDefinitionRepository
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $integrationConfig['attribute'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $integrationConfig['attribute'], $asset->getWorkspaceId()));

        if (!$attrDef->isTranslatable()) {
            $this->exportAndSaveTranscription($transcriptionId, $integrationId, $assetId);
        } else {
            foreach ($allEnabledLocales as $locale) {
                $attribute = $attributeIndex->getAttribute($attrDef->getId(), $locale);
                if (!empty($attribute)) {
                    continue;
                }
                if ($attrDef->isMultiple()) {
                    throw new \InvalidArgumentException(sprintf('Attribute "%s" must be mono-valued', $integrationConfig['attribute']));
                }

                if (AttributeInterface::NO_LOCALE !== $locale && 1 !== preg_match('/'.$locale.'/', $sourceLanguage)) {
                    $this->translateAndSave($transcriptionId, $integrationConfig['apiKey'], $integrationId, $assetId, $locale);
                } else {
                    $this->exportAndSaveTranscription($transcriptionId, $integrationId, $assetId, $locale);
                }
            }
        }
    }

    private function exportAndSaveTranscription($transcriptionId, $integrationId, $assetId, $locale = null): void
    {
        $this->bus->dispatch(new CreateExportMessage($transcriptionId, $integrationId, $assetId, $locale));
    }

    private function translateAndSave($sourceTranscriptionId, $happyScribeToken, $integrationId, $assetId, $targetLanguage): void
    {
        try {
            $resTranslate = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/task/transcription_translation', [
                'headers' => [
                    'Authorization' => 'Bearer '.$happyScribeToken,
                ],
                'json' => [
                    'source_transcription_id' => $sourceTranscriptionId,
                    'target_language' => $targetLanguage,
                ],
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Error when translate: '.$e->getMessage());
        }

        if (200 !== $resTranslate->getStatusCode()) {
            throw new \RuntimeException('Error when translate, response status: '.$resTranslate->getStatusCode().'target language'.$targetLanguage);
        }

        $resTranslateBody = $resTranslate->toArray();

        if ('failed' === $resTranslateBody['state']) {
            throw new \RuntimeException('Failed when translate: '.$resTranslateBody['failureReason']);
        }

        $this->bus->dispatch(new TranslateTranscriptionMessage($resTranslateBody['id'], $integrationId, $assetId, $targetLanguage), [new DelayStamp(5 * 1000)]);
    }
}
