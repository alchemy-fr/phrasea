<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
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

        $file = $this->em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $fileId, self::class);
        }

        $asset = $this->em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $assetId, self::class);
        }

        $this->assetManager->assignNewAssetSourceFile($asset, $file);
        $this->em->flush();
    }
}
