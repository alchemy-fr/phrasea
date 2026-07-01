<?php

declare(strict_types=1);

namespace App\Integration\Core\FileAnalyzer;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\State\JobState;
use App\Border\FileAnalyzer;
use App\Entity\Core\AssetRendition;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

final class FileAnalyzerAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const string JOB_ID = 'analyze';

    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $force = $inputs['rerun'] ?? false;
        $renditionId = $inputs['renditionId'] ?? null;
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        if ($renditionId) {
            /** @var AssetRendition $rendition */
            $rendition = DoctrineUtil::findStrict($this->em, AssetRendition::class, $renditionId);
            $file = $rendition->getFile();
            if (!$file) {
                throw new \InvalidArgumentException(sprintf('Rendition "%s" has no file', $rendition->getId()));
            }

        } else {
            $file = $asset->getSource();
            if (!$file) {
                throw new \InvalidArgumentException(sprintf('Asset "%s" has no source file', $asset->getId()));
            }
        }

        if ($this->fileAnalyzer->preAnalyzeFile($file, (array) $config, force: $force)) {
            $this->fileAnalyzer->analyzeFile($file, (array) $config, force: $force);
        }
        $this->em->persist($file);
        $this->em->flush();

        $context->setOutput('analysis', $file->getAnalysis());

        if (!$file->isAccepted()) {
            $context->setEndStatus(JobState::STATUS_FAILURE);
        }
    }
}
