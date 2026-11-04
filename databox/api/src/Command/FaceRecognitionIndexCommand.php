<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionAnalyze;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

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

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:face-recognition:index')
            ->setDescription('Backfill face detection for workspaces having the face recognition integration enabled')
            ->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Limit to a workspace ID')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Detect faces synchronously instead of dispatching messages')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
            if ($input->getOption('workspace') && $workspace->getId() !== $input->getOption('workspace')) {
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
                if ($input->getOption('sync')) {
                    $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
                    $asset = $this->em->find(Asset::class, $assetId);
                    $summary = $this->analyzer->analyze($asset, $config);
                    $io->writeln(sprintf('%s %s', $assetId, null !== $summary ? sprintf('<info>%d face(s)</info>', count($summary['faces'])) : '<comment>skipped</comment>'));
                    $this->em->clear();
                    $workspaceIntegration = $this->em->find(WorkspaceIntegration::class, $workspaceIntegration->getId());
                } else {
                    $this->bus->dispatch(new FaceRecognitionAnalyze($assetId, $workspaceIntegration->getId()));
                }
                ++$count;
            }
        }

        $io->success(sprintf('%d asset(s) %s', $count, $input->getOption('sync') ? 'processed' : 'queued'));

        return Command::SUCCESS;
    }
}
