<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe;

use Aws\TranscribeService\TranscribeServiceClient;
use RuntimeException;

class AwsTranscribeClient
{
    private function createClient(array $options): TranscribeServiceClient
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

    public function extractTextFromAudio(string $assetId, string $fileId, string $s3Uri, string $mimeType, array $options): array
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

    public function getJob(string $jobName, array $options): array
    {
        $client = $this->createClient($options);

        $response = $client->getTranscriptionJob([
            'TranscriptionJobName' => $jobName,
        ]);

        $job = $response['TranscriptionJob'];
        $status = $job['TranscriptionJobStatus'];

        if ('FAILED' === $status) {
            throw new RuntimeException(sprintf('Transcribe job "%s" has failed', $jobName));
        }

        if ('COMPLETED' !== $status) {
            throw new RuntimeException(sprintf('Transcribe job "%s" is not completed', $jobName));
        }

        return $job;
    }

    private function getMediaTypeFromMime(string $mimeType): ?string
    {
        switch ($mimeType) {
            case 'video/mp4':
                return 'mp4';
            case 'audio/mp3':
                return 'mp3';
            case 'audio/wav':
                return 'wav';
            case 'audio/flac':
                return 'flac';
            case 'audio/ogg':
                return 'ogg';
            case 'audio/amr':
                return 'amr';
            case 'audio/webm':
            case 'video/webm':
                return 'webm';
            default:
                return null;
        }
    }
}
