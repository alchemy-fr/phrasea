<?php

declare(strict_types=1);

namespace App\Command;

use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TriggerAssetIngestWorkflowCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetManager $assetManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:workflow:trigger-asset-ingest')
            ->addArgument('assetId', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $assetId = $input->getArgument('assetId');
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
