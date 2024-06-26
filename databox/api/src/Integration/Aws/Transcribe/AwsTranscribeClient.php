<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe;

use App\Integration\IntegrationConfig;
use Aws\TranscribeService\TranscribeServiceClient;

class AwsTranscribeClient
{
    private function createClient(IntegrationConfig $options): TranscribeServiceClient
    {
        return new TranscribeServiceClient([
            'region' => $options['region'],
            'credentials' => [
                'key' => $options['accessKeyId'],
                'secret' => $options['accessKeySecret'],
            ],
            'version' => 'latest',
        ]);
    }

    public function extractTextFromAudio(string $assetId, string $fileId, string $s3Uri, string $mimeType, IntegrationConfig $options): array
    {
        $client = $this->createClient($options);

        $res = $client->startTranscriptionJob([
            'IdentifyLanguage' => true,
            'IdentifyMultipleLanguages' => true,
            'Media' => [
                'MediaFileUri' => $s3Uri,
            ],
            'MediaFormat' => $this->getMediaTypeFromMime($mimeType),
            'Subtitles' => [
                'Formats' => [
                    'vtt',
                    'srt',
                ],
            ],
            'TranscriptionJobName' => sprintf('databox-%s-%s', $fileId, uniqid()),
            'Tags' => [
                ['Key' => 'assetId', 'Value' => $assetId],
                ['Key' => 'fileId', 'Value' => $fileId],
            ],
        ]);

        return $res->toArray();
    }

    public function getJob(string $jobName, IntegrationConfig $options): array
    {
        $client = $this->createClient($options);

        $response = $client->getTranscriptionJob([
            'TranscriptionJobName' => $jobName,
        ]);

        $job = $response['TranscriptionJob'];
        $status = $job['TranscriptionJobStatus'];

        if ('FAILED' === $status) {
            throw new \RuntimeException(sprintf('Transcribe job "%s" has failed', $jobName));
        }

        if ('COMPLETED' !== $status) {
            throw new \RuntimeException(sprintf('Transcribe job "%s" is not completed', $jobName));
        }

        return $job;
    }

    private function getMediaTypeFromMime(string $mimeType): ?string
    {
        return match ($mimeType) {
            'video/mp4' => 'mp4',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/flac' => 'flac',
            'audio/ogg' => 'ogg',
            'audio/amr' => 'amr',
            'audio/webm', 'video/webm' => 'webm',
            default => null,
        };
    }
}
