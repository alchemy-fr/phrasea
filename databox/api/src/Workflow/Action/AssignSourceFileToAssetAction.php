<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

readonly class AssignSourceFileToAssetAction implements ActionInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private EntityManagerInterface $em,
        private RenditionManager $renditionManager,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $fileId = $inputs['fileId'];
        $assetId = $inputs['assetId'];
        $renditionId = $inputs['renditionId'];

        $file = DoctrineUtil::findStrict($this->em, File::class, $fileId);
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);

        if (null !== $renditionId) {
            $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $renditionId);
            if ($renditionDefinition->getWorkspaceId() !== $asset->getWorkspaceId()) {
                throw new \InvalidArgumentException(sprintf('Rendition "%s" does not belong to the same workspace as the asset "%s"', $renditionDefinition->getId(), $asset->getId()));
            }

            $this->renditionManager->createOrReplaceRenditionFile(
                $asset,
                $renditionDefinition,
                $file,
                buildHash: null,
                moduleHashes: [],
                substituted: true,
                force: true,
                projection: false,
            );
            $this->em->flush();
        } else {
            $this->assetManager->assignNewAssetSourceFile(
                $asset,
                $file,
                $inputs['formData'] ?? [],
                $inputs['formLocale'] ?? null,
            );
            $this->em->flush();
        }
    }
}
