<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceManager;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Replays the recognition of the faces no user named, in the workspaces having face recognition
 * enabled: after a change of the match threshold, or to group faces detected with another setup.
 * No detection involved.
 */
#[AsCommand(
    name: 'app:face-recognition:relink',
    description: 'Replay the recognition of the detected faces against the persons (no detection involved)'
)]
class FaceRecognitionRelinkCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly FaceManager $faceManager,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Limit to a workspace ID', name: 'workspace', shortcut: 'w')]
        ?string $workspaceId = null,
    ): int {
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
            if (null !== $workspaceId && $workspace->getId() !== $workspaceId) {
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
