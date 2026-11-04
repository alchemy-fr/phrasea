<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\AttributeEntity;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceRecognitionConfigResolver;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionPhoto;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Asks for the photo of the persons that have none, or an automatic one, in the workspaces having
 * face recognition enabled with automatic photos: the persons that existed before the option.
 * A photo uploaded by a user is left alone.
 */
#[AsCommand(
    name: 'app:face-recognition:photos',
    description: 'Give the persons of the face recognition lists the photo of their best face',
)]
class FaceRecognitionPhotosCommand extends Command
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly FaceRecognitionConfigResolver $configResolver,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Limit to a workspace ID');
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

            try {
                $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
                $list = $this->configResolver->getEntityList($config);
            } catch (InvalidConfigurationException|\InvalidArgumentException $e) {
                $io->warning(sprintf('Workspace "%s": %s', $workspace->getName(), $e->getMessage()));
                continue;
            }
            if (!$config['autoPhoto']) {
                $io->writeln(sprintf('Workspace "%s": automatic photos are disabled', $workspace->getName()));
                continue;
            }

            $count = 0;
            foreach ($this->iterateEntityIds($list->getId()) as $entityId) {
                $this->bus->dispatch(new FaceRecognitionPhoto($entityId, $config->getIntegrationId()));
                ++$count;
            }
            $io->writeln(sprintf('Workspace "%s": <info>%d person(s)</info> queued', $workspace->getName(), $count));
        }

        $io->success('Done');

        return Command::SUCCESS;
    }

    /**
     * The entities of the list with no image, or an automatic one.
     *
     * @return iterable<string>
     */
    private function iterateEntityIds(string $listId): iterable
    {
        $lastId = null;
        do {
            $qb = $this->em->createQueryBuilder()
                ->select('e.id')
                ->from(AttributeEntity::class, 'e')
                ->leftJoin('e.image', 'i')
                ->andWhere('e.list = :list')
                ->andWhere('i.id IS NULL OR i.sourceAttributeId IS NOT NULL')
                ->setParameter('list', $listId)
                ->orderBy('e.id', 'ASC')
                ->setMaxResults(self::BATCH_SIZE);
            if (null !== $lastId) {
                $qb
                    ->andWhere('e.id > :lastId')
                    ->setParameter('lastId', $lastId);
            }
            $ids = array_map('strval', $qb->getQuery()->getSingleColumnResult());

            yield from $ids;

            $lastId = end($ids) ?: null;
        } while (self::BATCH_SIZE === count($ids));
    }
}
