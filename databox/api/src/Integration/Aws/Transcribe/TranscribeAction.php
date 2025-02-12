<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\ApiBudgetLimiter;
use App\Integration\IfActionInterface;
use App\Storage\S3Copier;

final class TranscribeAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly AwsTranscribeClient $client,
        private readonly S3Copier $s3Copier,
        private readonly ApiBudgetLimiter $apiBudgetLimiter,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);
        $file = $asset->getSource();

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

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return FileUtil::isVideoType($asset->getSource()->getType());
    }
}
