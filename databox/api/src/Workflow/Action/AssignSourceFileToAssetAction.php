<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;

readonly class AssignSourceFileToAssetAction implements ActionInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $fileId = $inputs['fileId'];
        $assetId = $inputs['assetId'];

        $file = DoctrineUtil::findStrict($this->em, File::class, $fileId);
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);

        $this->assetManager->assignNewAssetSourceFile(
            $asset,
            $file,
            $inputs['formData'] ?? [],
        );
        $this->em->flush();
    }
}
