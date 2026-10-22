<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\WorkspaceAssetIngester;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs the ingest workflow on the assets as they are, without touching their source:
 * the analysis, the duplicate links and the renditions of the asset are left to the workflow.
 *
 * The assets are walked by ID, with no user rights involved: this is a maintenance pass on
 * every asset of the workspaces (e.g. right after loading the fixtures), not a listing for a user.
 *
 * When the workflow transports are synchronous (dev), every job of an asset runs inline.
 */
#[AsCommand(
    name: 'app:workspace:ingest-assets',
    description: 'Trigger the ingest workflow of the assets of workspaces',
)]
final class IngestWorkspaceAssetsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ManagerRegistry $doctrine,
        private readonly AssetRepository $assetRepository,
        private readonly WorkspaceAssetIngester $workspaceAssetIngester,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('workspaces', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'IDs or slugs of the workspaces (all the workspaces by default)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $workspaces = $this->getWorkspaces($input->getArgument('workspaces'));

        $assetIds = [];
        foreach ($workspaces as $workspace) {
            foreach ($this->assetRepository->iterateIdsByWorkspace($workspace->getId()) as $assetId) {
                $assetIds[] = (string) $assetId;
            }
        }
        $this->em->clear();

        $ingested = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $io->createProgressBar(count($assetIds));
        $progressBar->start();
        foreach ($assetIds as $assetId) {
            try {
                $asset = $this->em->find(Asset::class, $assetId);
                if (null === $asset?->getSource()) {
                    ++$skipped;
                } else {
                    $this->workspaceAssetIngester->ingestAsset($asset, null);
                    ++$ingested;
                }
            } catch (\Throwable $e) {
                ++$errors;
                $progressBar->clear();
                $io->error(sprintf('Asset "%s": %s', $assetId, $e->getMessage()));
                $progressBar->display();

                if (!$this->em->isOpen()) {
                    $this->doctrine->resetManager();
                }
            }

            $this->em->clear();
            $progressBar->advance();
        }
        $progressBar->finish();
        $io->newLine(2);

        $summary = sprintf('%d asset(s) ingested, %d skipped (no source), %d error(s)', $ingested, $skipped, $errors);
        if ($errors > 0) {
            $io->error($summary);

            return Command::FAILURE;
        }

        $io->success($summary);

        return Command::SUCCESS;
    }

    /**
     * @param string[] $references
     *
     * @return Workspace[]
     */
    private function getWorkspaces(array $references): array
    {
        $repository = $this->em->getRepository(Workspace::class);
        if (empty($references)) {
            return $repository->findAll();
        }

        $workspaces = [];
        foreach ($references as $reference) {
            $workspace = Uuid::isValid($reference)
                ? $repository->find($reference)
                : $repository->findOneBy(['slug' => $reference]);

            if (!$workspace instanceof Workspace) {
                throw new \InvalidArgumentException(sprintf('Workspace "%s" not found', $reference));
            }
            $workspaces[] = $workspace;
        }

        return $workspaces;
    }
}
