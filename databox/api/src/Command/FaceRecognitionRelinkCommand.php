<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceManager;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Replays the recognition of the faces no user named, in the workspaces having face recognition
 * enabled: after a change of the match threshold, or to group faces detected with another setup.
 * No detection involved.
 */
class FaceRecognitionRelinkCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly FaceManager $faceManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:face-recognition:relink')
            ->setDescription('Replay the recognition of the detected faces against the persons (no detection involved)')
            ->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Limit to a workspace ID')
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

        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $workspace = $workspaceIntegration->getWorkspace();
            if (null === $workspace) {
                continue;
            }
            if ($input->getOption('workspace') && $workspace->getId() !== $input->getOption('workspace')) {
                continue;
            }

            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $changed = $this->faceManager->relink($config);
            $io->writeln(sprintf('Workspace "%s": <info>%d face(s)</info> changed person (threshold %.2f)', $workspace->getName(), $changed, (float) $config['matchThreshold']));
        }

        $io->success('Done');

        return Command::SUCCESS;
    }
}
