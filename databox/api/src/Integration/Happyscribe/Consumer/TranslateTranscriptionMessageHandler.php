<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class TranslateTranscriptionMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private HttpClientInterface $happyscribeClient,
    ) {
    }

    public function __invoke(TranslateTranscriptionMessage $message): void
    {
        $failureTranslateMessage = '';
        $translatedTranscriptionId = '';

        $translateId = $message->getTranslateId();
        $config = json_decode($message->getConfig(), true, 512, JSON_THROW_ON_ERROR);
        $happyscribeToken = $config['apiKey'];

        $resCheckTranslate = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/task/transcription_translation/'.$translateId, [
            'headers' => [
                'Authorization' => 'Bearer '.$happyscribeToken,
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

        if (!in_array($checkTranslateStatus, ['done', 'failed'])) {
            $delay = (int) (3 * $message->getDelay());

            $this->bus->dispatch(new TranslateTranscriptionMessage($translateId, $message->getConfig(), $delay), [new DelayStamp($delay)]);

            return;
        }

        if ('done' != $checkTranslateStatus) {
            throw new \RuntimeException('error when translate : '.$failureTranslateMessage);
        }

        $this->bus->dispatch(new CreateExportMessage($translatedTranscriptionId, json_encode($config)));
    }
}
