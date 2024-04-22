<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Model\Workflow;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Border\UriDownloader;
use App\Entity\Core\Attribute;
use App\Integration\Aws\AbstractAwsIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Alchemy\CoreBundle\Util\LocaleUtil;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class AwsTranscribeIntegration extends AbstractAwsIntegration implements WorkflowIntegrationInterface
{
    private const VERSION = '1.0';
    final public const SNS_PREFIX = 'databox:integration:transcribe:';

    public function __construct(
        private readonly AwsTranscribeClient $client,
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly UriDownloader $fileDownloader
    ) {
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

    public function getWorkflowJobDefinitions(array $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            TranscribeAction::class,
        );
    }

    public function handlePostComplete(array $config, array $args): void
    {
        $detail = $args['message']['detail'];
        $jobName = $detail['TranscriptionJobName'];
        $job = $this->client->getJob($jobName, $config);
        $attributesConfig = $config['attributes'];

        $input = new AssetAttributeBatchUpdateInput();
        $locale = LocaleUtil::normalizeLocale($job['LanguageCode']);

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
