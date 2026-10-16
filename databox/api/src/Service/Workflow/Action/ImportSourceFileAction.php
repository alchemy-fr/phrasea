<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Service\Asset\FileImporter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import the asset source file before any integration processes it.
 *
 * Never skipped (a skipped need blocks the integration jobs): a no-op when
 * there is nothing to import.
 */
readonly class ImportSourceFileAction implements ActionInterface
{
    public function __construct(
        private FileImporter $fileImporter,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $context->getInputs()['assetId']);
        $file = $asset->getSource();

        if (null !== $file && $file->isImportPending()) {
            $this->fileImporter->importFile($file);
        }
    }
}
