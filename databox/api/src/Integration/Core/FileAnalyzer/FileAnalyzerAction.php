<?php

declare(strict_types=1);

namespace App\Integration\Core\FileAnalyzer;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\State\JobState;
use App\Border\FileAnalyzer;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetStatusEnum;
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

        $analyzersConfig = [
            'analyzers' => $config['analyzers'] ?? [],
        ];

        $actions = [];

        $shouldAnalyze = $this->fileAnalyzer->shouldAnalyze($file, $analyzersConfig, force: $force);
        if ($shouldAnalyze) {
            if ($this->fileAnalyzer->preAnalyzeFile($file, $analyzersConfig)) {
                $this->fileAnalyzer->analyzeFile($file, $analyzersConfig);
            }

            $this->em->persist($file);

            $analysis = $file->getAnalysis();
            foreach ($analysis['results'] as $analyzerOutput) {
                if (!empty($analyzerOutput['actions'])) {
                    $actions = array_merge(array_map(fn (string $a): FileAnalyzerAssetActionEnum => FileAnalyzerAssetActionEnum::from($a), $actions), $analyzerOutput['actions']);
                    break;
                }
            }

            $context->setOutput('analysis', $analysis);
        }
        $context->setOutput('analyzed', $shouldAnalyze);

        if ($file->isAccepted()) {
            if (AssetStatusEnum::Accepted !== $asset->getStatus()) {
                $asset->setStatus(AssetStatusEnum::Accepted);
                $this->em->persist($asset);
            }
        } else {
            $context->setEndStatus(JobState::STATUS_FAILURE);

            $actionsOnReject = array_merge($actions, $config['actions_on_reject'] ?? []);

            if (in_array(FileAnalyzerAssetActionEnum::DELETE->value, $actionsOnReject, true)) {
                $actionsOnReject = [FileAnalyzerAssetActionEnum::DELETE];
            }

            foreach ($actionsOnReject as $action) {
                $action = FileAnalyzerAssetActionEnum::from($action);

                switch ($action) {
                    case FileAnalyzerAssetActionEnum::QUARANTINE:
                        $asset->setStatus(AssetStatusEnum::Quarantined);
                        $this->em->persist($asset);
                        break;
                    case FileAnalyzerAssetActionEnum::DELETE:
                        $this->em->remove($file);
                        $this->em->remove($asset);
                        break;
                    case FileAnalyzerAssetActionEnum::MOVE_TO_TRASH:
                        $asset->delete();
                        $this->em->persist($asset);
                        break;
                }
            }
        }

        $this->em->flush();
    }
}
