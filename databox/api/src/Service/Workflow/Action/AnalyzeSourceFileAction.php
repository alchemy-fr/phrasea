<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\State\JobState;
use App\Border\FileAnalyzer;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;

readonly class AnalyzeSourceFileAction implements ActionInterface
{
    public function __construct(
        private FileAnalyzer $fileAnalyzer,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $assetId = $inputs['assetId'];
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);

        $file = $asset->getSource();
        if (!$file) {
            throw new \InvalidArgumentException(sprintf('Asset "%s" has no source file', $asset->getId()));
        }

        $this->fileAnalyzer->analyzeFile($file, force: $inputs['rerun'] ?? false);
        $this->em->persist($file);
        $this->em->flush();

        $context->setOutput('analysis', $file->getAnalysis());

        if (!$file->isAccepted()) {
            $context->setEndStatus(JobState::STATUS_FAILURE);
        }
    }
}
