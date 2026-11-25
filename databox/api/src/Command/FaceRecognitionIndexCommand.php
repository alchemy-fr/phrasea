<?php

declare(strict_types=1);

namespace App\Command;

use App\Consumer\MessageDeduplication;
use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionAnalyze;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:face-recognition:index',
    description: 'Backfill face detection for workspaces having the face recognition integration enabled'
)]
class FaceRecognitionIndexCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly FaceRecognitionAnalyzer $analyzer,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Limit to a workspace ID', name: 'workspace', shortcut: 'w')]
        ?string $workspaceId = null,
        #[Option('Detect faces synchronously instead of dispatching messages')]
        bool $sync = false,
    ): int {
        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)->findBy([
            'integration' => FaceRecognitionIntegration::getName(),
            'enabled' => true,
        ]);
        if (empty($workspaceIntegrations)) {
            $io->warning('No enabled face recognition integration found');

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
                    $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
                    $asset = $this->em->find(Asset::class, $assetId);
                    $faces = $this->analyzer->analyze($asset, $config);
                    $io->writeln(sprintf('%s %s', $assetId, null !== $faces ? sprintf('<info>%d face(s)</info>', count($faces)) : '<comment>skipped</comment>'));
                    $this->em->clear();
                    $workspaceIntegration = $this->em->find(WorkspaceIntegration::class, $workspaceIntegration->getId());
                } else {
                    $this->bus->dispatch(MessageDeduplication::coalesce(new FaceRecognitionAnalyze($assetId, $workspaceIntegration->getId()), 900));
                }
                ++$count;
            }
        }

        $io->success(sprintf('%d asset(s) %s', $count, $sync ? 'processed' : 'queued'));

        return Command::SUCCESS;
    }
}
