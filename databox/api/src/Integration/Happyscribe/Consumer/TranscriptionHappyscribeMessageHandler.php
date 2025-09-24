<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use App\Attribute\AttributeInterface;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class TranscriptionHappyscribeMessageHandler
{
    private string $happyscribeToken;
    private array $config;

    public function __construct(
        private MessageBusInterface $bus,
        private HttpClientInterface $happyscribeClient,
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(TranscriptionHappyscribeMessage $message): void
    {
        $transcriptionId = $message->getTranscriptionId();
        $conf = $message->getConfig();
        $this->config = json_decode($conf, true, 512, JSON_THROW_ON_ERROR);
        $this->happyscribeToken = $this->config['apiKey'];

        $asset = $this->em->find(Asset::class, $this->config['assetId']);
        $allEnabledLocales = $asset->getWorkspace()->getEnabledLocales();

        $failureTranscriptMessage = '';

        $resCheckTranscript = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/transcriptions/'.$transcriptionId, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->config['apiKey'],
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

        if (!in_array($transcriptStatus, ['automatic_done', 'locked', 'failed'])) {
            $delay = (int) (3 * $message->getDelay());

            $this->bus->dispatch(new TranscriptionHappyscribeMessage($transcriptionId, $message->getConfig(), $delay), [new DelayStamp($delay)]);

            return;
        }

        if ('automatic_done' != $transcriptStatus) {
            throw new \RuntimeException('transcription failed, status : '.$transcriptStatus.', message : '.$failureTranscriptMessage);
        }

        if (!$this->config['isTranslatableAttribute']) {
            $this->exportAndSaveTranscription($transcriptionId);
        } else {
            foreach ($allEnabledLocales as $locale) {
                if ($this->config['isMultipleAttribute']) {
                    throw new \InvalidArgumentException(sprintf('Attribute "%s" must be mono-valued', $this->config['attributeId']));
                }

                if (AttributeInterface::NO_LOCALE !== $locale && 1 !== preg_match('/'.$locale.'/', $this->config['sourceLanguage'])) {
                    $this->translateAndSave($transcriptionId, $locale);
                } else {
                    $this->exportAndSaveTranscription($transcriptionId, $locale);
                }
            }
        }
    }

    private function exportAndSaveTranscription($transcriptionId, $locale = null): void
    {
        $this->config['locale'] = $locale;
        $this->bus->dispatch(new CreateExportMessage($transcriptionId, json_encode($this->config)));
    }

    private function translateAndSave($sourceTranscriptionId, $targetLanguage): void
    {
        try {
            $resTranslate = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/task/transcription_translation', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->happyscribeToken,
                ],
                'json' => [
                    'source_transcription_id' => $sourceTranscriptionId,
                    'target_language' => $targetLanguage,
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

        $this->config['locale'] = $targetLanguage;
        $this->bus->dispatch(new TranslateTranscriptionMessage($resTranslateBody['id'], json_encode($this->config)), [new DelayStamp(5 * 1000)]);
    }
}
