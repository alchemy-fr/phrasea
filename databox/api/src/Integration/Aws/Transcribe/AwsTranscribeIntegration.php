<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Border\UriDownloader;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Integration\ApiBudgetLimiter;
use App\Integration\AssetOperationIntegrationInterface;
use App\Integration\Aws\AbstractAwsIntegration;
use App\Storage\S3Copier;
use App\Util\FileUtil;
use App\Util\LocaleUtils;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class AwsTranscribeIntegration extends AbstractAwsIntegration implements AssetOperationIntegrationInterface
{
    private const VERSION = '1.0';
    final public const SNS_PREFIX = 'databox:integration:transcribe:';

    public function __construct(private readonly AwsTranscribeClient $client, private readonly S3Copier $s3Copier, private readonly ApiBudgetLimiter $apiBudgetLimiter, private readonly BatchAttributeManager $batchAttributeManager, private readonly UriDownloader $fileDownloader)
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $this->addCredentialConfigNode($builder);
        $this->addRegionConfigNode($builder);

        $builder
            ->arrayNode('attributes')
                ->children()
                    ->scalarNode('language')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('vtt')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('srt')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('transcript')
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end()
            ->scalarNode('workloadS3Bucket')
                ->isRequired()
                ->cannotBeEmpty()
                ->example('my-source-bucket')
            ->end()
        ;

        $builder->append($this->createBudgetLimitConfigNode(true));
    }

    protected function getSupportedRegions(): array
    {
        return [
            'ap-northeast-1',
            'ap-northeast-2',
            'ap-south-1',
            'ap-southeast-1',
            'ap-southeast-2',
            'eu-central-1',
            'eu-west-1',
            'eu-west-2',
            'eu-west-3',
            'us-east-1',
            'us-east-2',
            'us-west-2',
        ];
    }

    public function handleAsset(Asset $asset, array $config): void
    {
        $this->transcribe($asset, $asset->getSource(), $config);
    }

    private function transcribe(Asset $asset, File $file, array $config): void
    {
        if ($this->supportFile($file)) {
            $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

            $key = sprintf('workload/%s-%s%s', $file->getId(), uniqid(), $file->getExtensionWithDot());

            $this->s3Copier->copyToS3($file, $config['workloadS3Bucket'], $key, [
                'region' => $config['region'],
                'accessKeyId' => $config['accessKeyId'],
                'accessKeySecret' => $config['accessKeySecret'],
            ]);

            $s3Uri = sprintf('s3://%s/%s', $config['workloadS3Bucket'], $key);

            $this->client->extractTextFromAudio($asset->getId(), $file->getId(), $s3Uri, $file->getType(), $config);
        }
    }

    public function handlePostComplete(array $config, array $args): void
    {
        $detail = $args['message']['detail'];
        $jobName = $detail['TranscriptionJobName'];
        $job = $this->client->getJob($jobName, $config);
        $attributesConfig = $config['attributes'];

        $input = new AssetAttributeBatchUpdateInput();
        $locale = LocaleUtils::normalizeLocale($job['LanguageCode']);

        if (!empty($attributesConfig['language'])) {
            $input->actions[] = $this->createAttribute(
                $attributesConfig['language'],
                $locale,
                $job['IdentifiedLanguageScore'],
                null
            );
        }

        if (!empty($attributesConfig['transcript'])) {
            $transcriptUri = $job['Transcript']['TranscriptFileUri'] ?? null;
            if (null !== $transcriptUri) {
                $transcriptFile = $this->fileDownloader->download($transcriptUri);
                $input->actions[] = $this->createAttribute(
                    $attributesConfig['transcript'],
                    file_get_contents($transcriptFile),
                    null,
                    $locale
                );
            }
        }

        if (isset($job['Subtitles'])) {
            $subtitles = $job['Subtitles'];

            $formatAttrs = [
                'vtt' => 'vtt',
                'srt' => 'srt',
            ];
            foreach ($subtitles['SubtitleFileUris'] as $subtitleUri) {
                $extension = FileUtil::getExtensionFromPath($subtitleUri);
                $format = $formatAttrs[$extension];
                if (!empty($attributesConfig[$format])) {
                    $subtitleFile = $this->fileDownloader->download($subtitleUri);

                    $input->actions[] = $this->createAttribute(
                        $attributesConfig[$format],
                        file_get_contents($subtitleFile),
                        null,
                        $locale
                    );
                }
            }
        }

        $assetId = $this->getTagByKey($job['Tags'], 'assetId');

        $this->batchAttributeManager->handleBatch(
            $config['workspaceId'],
            [$assetId],
            $input,
            null
        );
    }

    private function createAttribute(string $name, $value, ?float $confidence, ?string $locale): AttributeActionInput
    {
        $i = new AttributeActionInput();
        $i->originVendor = self::getName();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendorContext = 'v'.self::VERSION;
        $i->name = $name;
        $i->confidence = $confidence;
        $i->value = $value;
        $i->locale = $locale;

        return $i;
    }

    public function supportsAsset(Asset $asset, array $config): bool
    {
        return $asset->getSource() && $this->supportFile($asset->getSource());
    }

    private function supportFile(File $file): bool
    {
        return FileUtil::isVideoType($file->getType());
    }

    public function supportsFileActions(File $file, array $config): bool
    {
        return $this->supportFile($file);
    }

    public function getMessageId(string $fileId): string
    {
        return self::SNS_PREFIX.$fileId;
    }

    public static function getName(): string
    {
        return 'aws.transcribe';
    }

    public static function getTitle(): string
    {
        return 'AWS Transcribe';
    }
}
