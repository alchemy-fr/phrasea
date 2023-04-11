<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Doctrine\ORM\EntityManagerInterface;

class AssignSourceFileToAssetHandler implements ActionInterface
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $fileId = $context->getOutputs()['fileId'];
        $assetId = $inputs['assetId'];

        $file = $this->em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $fileId, __CLASS__);
        }

        $asset = $this->em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $assetId, __CLASS__);
        }

        $this->assetManager->assignNewAssetSourceFile($asset, $file);
    }
}
