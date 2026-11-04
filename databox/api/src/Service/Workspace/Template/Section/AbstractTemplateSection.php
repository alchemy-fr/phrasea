<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use App\Service\Workspace\Template\WorkspaceTemplateSectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTemplateSection implements WorkspaceTemplateSectionInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T[]
     */
    protected function findByWorkspace(string $class, Workspace $workspace, array $orderBy = []): array
    {
        return $this->em->getRepository($class)->findBy([
            'workspace' => $workspace->getId(),
        ], $orderBy ?: null);
    }

    protected function logUpsert(string $type, ?string $name, bool $created): void
    {
        $this->logger->info(sprintf('%s %s "%s"', $created ? 'Creating' : 'Updating', $type, $name));
    }

    /**
     * Owner of an imported entity: the exported one, if the template carries the owners.
     */
    protected function resolveOwnerId(array $item, TemplateImportContext $context): ?string
    {
        return $item['ownerId'] ?? $context->workspace->getOwnerId();
    }

    protected function exportOwnerId(?string $ownerId, WorkspaceTemplateOptions $options): array
    {
        return $options->withAccessControl ? ['ownerId' => $ownerId] : [];
    }
}
