<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationRegistry;
use App\Security\Voter\AbstractVoter;
use App\Service\Duplicate\CopyNameGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a disabled copy of an integration, owned by the current user, without its data, tokens nor ACEs.
 */
final class DuplicateWorkspaceIntegrationProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;
    use WithOwnerIdProcessorTrait;

    private const int NAME_MAX_LENGTH = 255;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly CopyNameGenerator $copyNameGenerator,
        private readonly IntegrationRegistry $integrationRegistry,
    ) {
    }

    /**
     * @param WorkspaceIntegration $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WorkspaceIntegration
    {
        $integration = $this->integrationRegistry->getIntegration((string) $data->getIntegration());

        $copy = new WorkspaceIntegration();
        $copy->setWorkspace($data->getWorkspace());
        $copy->setIntegration($data->getIntegration());
        $copy->setName($this->copyNameGenerator->generate(
            $data->getName() ?: ($integration?->getDisplayName() ?? (string) $data->getIntegration()),
            fn (string $name): bool => $this->isNameTaken($data, $name),
            self::NAME_MAX_LENGTH,
        ));
        $copy->setPublic($data->getPublic());
        $copy->setIf($data->getIf());
        foreach ($data->getNeeds() as $need) {
            $copy->addNeed($need);
        }
        // Not to be triggered twice
        $copy->setEnabled(false);
        $copy->setConfig(null !== $integration
            ? $integration->prepareDuplicateConfiguration($data->getConfig())
            : $data->getConfig());
        $this->processOwnerId($copy);

        $this->denyAccessUnlessGranted(AbstractVoter::CREATE, $copy);
        $this->validator->validate($copy, ['groups' => ['Default', 'create']]);

        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }

    private function isNameTaken(WorkspaceIntegration $source, string $name): bool
    {
        $queryBuilder = $this->em->getRepository(WorkspaceIntegration::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.integration = :integration')
            ->andWhere('t.name = :name')
            ->setParameter('integration', $source->getIntegration())
            ->setParameter('name', $name);

        if (null !== $workspaceId = $source->getWorkspaceId()) {
            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspaceId);
        } else {
            $queryBuilder->andWhere('t.workspace IS NULL');
        }

        return 0 < (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
}
