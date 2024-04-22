<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use App\Integration\IntegrationManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TranscribeJobStatusChangedHandler
{
    public function __construct(private IntegrationManager $integrationManager)
    {
    }

    public function __invoke(TranscribeJobStatusChanged $message): void
    {
        $msg = $message->getMessage();
        $detail = $msg['detail'];

        if ('COMPLETED' === $detail['TranscriptionJobStatus']) {
            $this->integrationManager->callIntegrationFunction($message->getIntegrationId(), 'handlePostComplete', [
                'message' => $msg,
            ]);
        }

        /*
         *   array:16 [
    "TranscriptionJobName" => "f314ff7e597268634664f7bb6bb5d2a1"
    "TranscriptionJobStatus" => "COMPLETED"
    "LanguageCode" => "fr-FR"
    "MediaSampleRateHertz" => 48000
    "MediaFormat" => "mp4"
    "Media" => array:1 [
      "MediaFileUri" => "s3://alchemy-transcribe-workload/workload/aec291ea-8f53-4605-9ab4-349a70f70408-63b4599373919.mp4"
    ]
    "Transcript" => array:1 [
      "TranscriptFileUri" => "https://s3.eu-west-3.amazonaws.com/aws-transcribe-eu-west-3-prod/122649456891/f314ff7e597268634664f7bb6bb5d2a1/c32d3bf9-97bf-43bb-a5b9-3425ad33387c/asrOutput.json?X-Amz
  -Security-Token=IQoJb3JpZ2luX2VjEJH%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCWV1LXdlc3QtMyJHMEUCIFAlXV8M%2BU3sAIBt1Y7g5gWJzJc43Aj06xffjRC4xGP3AiEAkJLECrlb3ATGKDDG9I%2FieL2jW5t8j26bU6gjN9%2B9TZMq1gQI2v%2F%
  2F%2F%2F%2F%2F%2F%2F%2F%2FARAEGgwxNzQwODA2NTMxOTgiDEiFlKTeCC5gry8lDSqqBEzXKPTUoiBxUcvnMr3HnOY6jMzy0wnwuBN09bjYBTDnloJQlr8CF%2FqD9Ixs8fiqA6LDT5aza3jBtSktq3h15kdaKO6JOSGzhadZVC8OtuBzHtKIVX%2Bzdeqx7e
  kcfKdN0WMjhxYK28HAL6jXHW1%2F7Sd7Yc4F6xLUdeBoxMjl6bQTLaiKqAHphdkSWGKlnloEkpzhvsiMtZ5hBIi3hQa5ZzOX1dMYFp%2BPGJv9TiRr7TijP6IuGvpgRK8lvZOSG1FpgB8AIUcQvIGGOZXTwyr%2FTQsdfYl0DIGhVMfA7ZwN3sn8gDH8TVMnVWtK
  KG3xgQI4WFLQPp10W7kFoBxdhLgbNkDWV6NVp74f7cekAw%2BKA7sCZqPnooxvj8UIIVyEC%2BEN6sDHSt%2BQiLv0ybichQE3RghrBZFqK7fEziy%2FlN5PqGOD1s69Rx0%2ByIYKF73cpttZ%2BXgJw246tkdE45RTjFxJXSThTh07UKhpx28WP8rmNYum%2FB
  eUWhp3tq51WUQfF%2FYimTN0dS2L2IiKOm0F96F1rkZZNSZUni9U1yvFQI6TVqKTdrj9BLJIoCTBP4%2F8t%2BWCiSQ%2FWj7DnWa17iLa3yWowAJc7b8yE3O6XrD%2FRmD8nEl78wUMwGqtFB0ul4GIDKZdZ1buMkgM8BjIVHVKyDVaTsMktendy5UH9D7vVO32
  u8QY7mDyKfC99HjvhI8nLHANIDKXudc4F3lq0Sg7fhNHHLcuSB90kqxmfhCTMJW60Z0GOqkBV1LTMPgRTiVWoHFmWdZ%2Ffi9khJc2GD2j9Y5DIYiiGCJq8esJOgR%2FXoL1yWhDxobbe4REdKMth66yg%2Fp0qan4Y6zc7JSkerd2KarqlEpKW59bcRDFZHvxFo
  uy5MuwU93Xi4mZiBia3IoZ61QmCXd5p5cMPOnNXfYKfgBrbYqgguBh3jOnx1UzPAWkIwzFOHkNvD1y%2FdlOiG2tUeYA8eq5B8xEGaIhTNExZQ%3D%3D&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20230103T165210Z&X-Amz-SignedHeader
  s=host&X-Amz-Expires=900&X-Amz-Credential=ASIASRCAD66HGYD3AVW7%2F20230103%2Feu-west-3%2Fs3%2Faws4_request&X-Amz-Signature=2fe24842d1bb47aaba8fcc680f81ac8c5e65798d559f855618a6a296c646980c"
    ]
    "StartTime" => Aws\Api\DateTimeResult @1672763796 {#1575
      date: 2023-01-03 16:36:36.258 UTC (+00:00)
    }
    "CreationTime" => Aws\Api\DateTimeResult @1672763796 {#1589
      date: 2023-01-03 16:36:36.240 UTC (+00:00)
    }
    "CompletionTime" => Aws\Api\DateTimeResult @1672763826 {#1588
      date: 2023-01-03 16:37:06.336 UTC (+00:00)
    }
    "Settings" => array:2 [
      "ChannelIdentification" => false
      "ShowAlternatives" => false
    ]
    "IdentifyLanguage" => true
    "IdentifyMultipleLanguages" => true
    "IdentifiedLanguageScore" => 0.99562782049179
    "LanguageCodes" => array:1 [
      0 => array:2 [
        "LanguageCode" => "fr-FR"
        "DurationInSeconds" => 15.529999732971
      ]
    ]
    "Subtitles" => array:2 [
      "Formats" => array:2 [
        0 => "vtt"
        1 => "srt"
      ]
      "SubtitleFileUris" => array:2 [
        0 => "https://s3.eu-west-3.amazonaws.com/aws-transcribe-eu-west-3-prod/122649456891/f314ff7e597268634664f7bb6bb5d2a1/c32d3bf9-97bf-43bb-a5b9-3425ad33387c/srtSubtitles.srt?X-Amz-Security-Toke
  n=IQoJb3JpZ2luX2VjEJH%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCWV1LXdlc3QtMyJHMEUCIFAlXV8M%2BU3sAIBt1Y7g5gWJzJc43Aj06xffjRC4xGP3AiEAkJLECrlb3ATGKDDG9I%2FieL2jW5t8j26bU6gjN9%2B9TZMq1gQI2v%2F%2F%2F%2F%2F%2F
  %2F%2F%2F%2FARAEGgwxNzQwODA2NTMxOTgiDEiFlKTeCC5gry8lDSqqBEzXKPTUoiBxUcvnMr3HnOY6jMzy0wnwuBN09bjYBTDnloJQlr8CF%2FqD9Ixs8fiqA6LDT5aza3jBtSktq3h15kdaKO6JOSGzhadZVC8OtuBzHtKIVX%2Bzdeqx7ekcfKdN0WMjhxYK
  28HAL6jXHW1%2F7Sd7Yc4F6xLUdeBoxMjl6bQTLaiKqAHphdkSWGKlnloEkpzhvsiMtZ5hBIi3hQa5ZzOX1dMYFp%2BPGJv9TiRr7TijP6IuGvpgRK8lvZOSG1FpgB8AIUcQvIGGOZXTwyr%2FTQsdfYl0DIGhVMfA7ZwN3sn8gDH8TVMnVWtKKG3xgQI4WFLQPp
  10W7kFoBxdhLgbNkDWV6NVp74f7cekAw%2BKA7sCZqPnooxvj8UIIVyEC%2BEN6sDHSt%2BQiLv0ybichQE3RghrBZFqK7fEziy%2FlN5PqGOD1s69Rx0%2ByIYKF73cpttZ%2BXgJw246tkdE45RTjFxJXSThTh07UKhpx28WP8rmNYum%2FBeUWhp3tq51WUQf
  F%2FYimTN0dS2L2IiKOm0F96F1rkZZNSZUni9U1yvFQI6TVqKTdrj9BLJIoCTBP4%2F8t%2BWCiSQ%2FWj7DnWa17iLa3yWowAJc7b8yE3O6XrD%2FRmD8nEl78wUMwGqtFB0ul4GIDKZdZ1buMkgM8BjIVHVKyDVaTsMktendy5UH9D7vVO32u8QY7mDyKfC99H
  jvhI8nLHANIDKXudc4F3lq0Sg7fhNHHLcuSB90kqxmfhCTMJW60Z0GOqkBV1LTMPgRTiVWoHFmWdZ%2Ffi9khJc2GD2j9Y5DIYiiGCJq8esJOgR%2FXoL1yWhDxobbe4REdKMth66yg%2Fp0qan4Y6zc7JSkerd2KarqlEpKW59bcRDFZHvxFouy5MuwU93Xi4mZ
  iBia3IoZ61QmCXd5p5cMPOnNXfYKfgBrbYqgguBh3jOnx1UzPAWkIwzFOHkNvD1y%2FdlOiG2tUeYA8eq5B8xEGaIhTNExZQ%3D%3D&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20230103T165210Z&X-Amz-SignedHeaders=host&X-Amz-E
  xpires=900&X-Amz-Credential=ASIASRCAD66HGYD3AVW7%2F20230103%2Feu-west-3%2Fs3%2Faws4_request&X-Amz-Signature=27bd09454f034a8dc628c4214fd9f7be2c1441e05a461a1e9b53c4e31afb9943"
        1 => "https://s3.eu-west-3.amazonaws.com/aws-transcribe-eu-west-3-prod/122649456891/f314ff7e597268634664f7bb6bb5d2a1/c32d3bf9-97bf-43bb-a5b9-3425ad33387c/vttSubtitles.vtt?X-Amz-Security-Toke
  n=IQoJb3JpZ2luX2VjEJH%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCWV1LXdlc3QtMyJHMEUCIFAlXV8M%2BU3sAIBt1Y7g5gWJzJc43Aj06xffjRC4xGP3AiEAkJLECrlb3ATGKDDG9I%2FieL2jW5t8j26bU6gjN9%2B9TZMq1gQI2v%2F%2F%2F%2F%2F%2F
  %2F%2F%2F%2FARAEGgwxNzQwODA2NTMxOTgiDEiFlKTeCC5gry8lDSqqBEzXKPTUoiBxUcvnMr3HnOY6jMzy0wnwuBN09bjYBTDnloJQlr8CF%2FqD9Ixs8fiqA6LDT5aza3jBtSktq3h15kdaKO6JOSGzhadZVC8OtuBzHtKIVX%2Bzdeqx7ekcfKdN0WMjhxYK
  28HAL6jXHW1%2F7Sd7Yc4F6xLUdeBoxMjl6bQTLaiKqAHphdkSWGKlnloEkpzhvsiMtZ5hBIi3hQa5ZzOX1dMYFp%2BPGJv9TiRr7TijP6IuGvpgRK8lvZOSG1FpgB8AIUcQvIGGOZXTwyr%2FTQsdfYl0DIGhVMfA7ZwN3sn8gDH8TVMnVWtKKG3xgQI4WFLQPp
  10W7kFoBxdhLgbNkDWV6NVp74f7cekAw%2BKA7sCZqPnooxvj8UIIVyEC%2BEN6sDHSt%2BQiLv0ybichQE3RghrBZFqK7fEziy%2FlN5PqGOD1s69Rx0%2ByIYKF73cpttZ%2BXgJw246tkdE45RTjFxJXSThTh07UKhpx28WP8rmNYum%2FBeUWhp3tq51WUQf
  F%2FYimTN0dS2L2IiKOm0F96F1rkZZNSZUni9U1yvFQI6TVqKTdrj9BLJIoCTBP4%2F8t%2BWCiSQ%2FWj7DnWa17iLa3yWowAJc7b8yE3O6XrD%2FRmD8nEl78wUMwGqtFB0ul4GIDKZdZ1buMkgM8BjIVHVKyDVaTsMktendy5UH9D7vVO32u8QY7mDyKfC99H
  jvhI8nLHANIDKXudc4F3lq0Sg7fhNHHLcuSB90kqxmfhCTMJW60Z0GOqkBV1LTMPgRTiVWoHFmWdZ%2Ffi9khJc2GD2j9Y5DIYiiGCJq8esJOgR%2FXoL1yWhDxobbe4REdKMth66yg%2Fp0qan4Y6zc7JSkerd2KarqlEpKW59bcRDFZHvxFouy5MuwU93Xi4mZ
  iBia3IoZ61QmCXd5p5cMPOnNXfYKfgBrbYqgguBh3jOnx1UzPAWkIwzFOHkNvD1y%2FdlOiG2tUeYA8eq5B8xEGaIhTNExZQ%3D%3D&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20230103T165210Z&X-Amz-SignedHeaders=host&X-Amz-E
  xpires=899&X-Amz-Credential=ASIASRCAD66HGYD3AVW7%2F20230103%2Feu-west-3%2Fs3%2Faws4_request&X-Amz-Signature=53ad9d8f8175dc7ab01ca110af931bc759d55a6d37c1bd30995228311d044b63"
      ]
    ]
  ]
         */
    }
}

final readonly class TranscribeJobStatusChanged
{
    public function __construct(private string $integrationId, private array $message)
    {
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getMessage(): array
    {
        return $this->message;
    }
}
