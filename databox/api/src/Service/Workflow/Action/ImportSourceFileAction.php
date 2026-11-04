<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\IfActionInterface;
use App\Service\Asset\FileImporter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import the asset source file before any integration processes it.
 * The job is skipped when the source is not to be imported.
 */
readonly class ImportSourceFileAction implements IfActionInterface
{
    public function __construct(
        private FileImporter $fileImporter,
        private EntityManagerInterface $em,
    ) {
    }

    public function evaluateIf(JobExecutionContext $context): bool
    {
        if (true !== $this->em->find(Asset::class, $context->getInputs()['assetId'])
            ?->getSource()
            ?->isImportPending()) {
            $context->setSkipReason('No source file import pending');

            return false;
        }

        return true;
    }

    public function handle(RunContext $context): void
    {
        $source = $this->em->find(Asset::class, $context->getInputs()['assetId'])?->getSource();
        if (null !== $source && $source->isImportPending()) {
            $this->fileImporter->importFile($source);
        }
    }
}
