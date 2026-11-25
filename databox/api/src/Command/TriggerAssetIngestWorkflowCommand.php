<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Asset;
use App\Service\Asset\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:workflow:trigger-asset-ingest')]
class TriggerAssetIngestWorkflowCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetManager $assetManager,
    ) {
        parent::__construct();
    }

    public function __invoke(
        #[Argument]
        string $assetId,
    ): int {
        $asset = $this->em->find(Asset::class, $assetId);

        if (!$asset instanceof Asset) {
            throw new \InvalidArgumentException('Asset '.$assetId.' not found');
        }

        if (null === $asset->getSource()) {
            throw new \InvalidArgumentException(sprintf('Asset "%s" has no source file', $assetId));
        }

        $this->assetManager->assignNewAssetSourceFile(
            $asset,
            $asset->getSource()
        );
        $this->em->flush();

        return 0;
    }
}
