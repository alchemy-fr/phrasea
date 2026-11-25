<?php

declare(strict_types=1);

namespace App\Command;

use App\Consumer\Handler\Similarity\SimilarityEmbed;
use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Similarity\SimilarityIntegration;
use App\Integration\IntegrationManager;
use App\Service\Vector\AssetEmbeddingManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:similarity:index',
    description: 'Backfill asset embeddings for workspaces having the similarity integration enabled'
)]
class SimilarityIndexCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly AssetEmbeddingManager $assetEmbeddingManager,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Limit to a workspace ID', name: 'workspace', shortcut: 'w')]
        ?string $workspaceId = null,
        #[Option('Compute embeddings synchronously instead of dispatching messages')]
        bool $sync = false,
        #[Option('Force re-computation of embeddings even if they already exist')]
        bool $force = false,
    ): int {
        $criteria = [
            'integration' => SimilarityIntegration::getName(),
            'enabled' => true,
        ];

        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)->findBy($criteria);
        if (empty($workspaceIntegrations)) {
            $io->warning('No enabled similarity integration found');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $workspace = $workspaceIntegration->getWorkspace();
            if (null === $workspace) {
                continue;
            }
            if (null !== $workspaceId && $workspace->getId() !== $workspaceId) {
                continue;
            }

            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $rendition = $config['rendition'];

            $io->section(sprintf('Workspace "%s"', $workspace->getName()));

            $assetIds = $this->em->createQueryBuilder()
                ->select('a.id')
                ->from(Asset::class, 'a')
                ->andWhere('a.workspace = :ws')
                ->andWhere('a.deletedAt IS NULL')
                ->setParameter('ws', $workspace->getId())
                ->getQuery()
                ->toIterable();

            foreach ($assetIds as $r) {
                $assetId = (string) $r['id'];
                if ($sync) {
                    $asset = $this->em->find(Asset::class, $assetId);
                    $done = $this->assetEmbeddingManager->embedAsset($asset, $rendition, $force);
                    $io->writeln(sprintf('%s %s', $assetId, $done ? '<info>OK</info>' : '<comment>skipped</comment>'));
                    $this->em->clear();
                } else {
                    $this->bus->dispatch(new SimilarityEmbed($assetId, $rendition));
                }
                ++$count;
            }
        }

        $io->success(sprintf('%d asset(s) %s', $count, $sync ? 'processed' : 'queued'));

        return Command::SUCCESS;
    }
}
