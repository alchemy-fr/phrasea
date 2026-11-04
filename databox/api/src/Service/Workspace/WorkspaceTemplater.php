<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use App\Entity\Core\Workspace;
use App\Entity\Template\WorkspaceTemplate;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use App\Service\Workspace\Template\WorkspaceTemplateSectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Exports the configuration of a workspace (everything but its content: assets, collections, files)
 * and imports it into a new or an existing workspace. Each part is handled by a section
 * (see WorkspaceTemplateSectionInterface).
 */
final readonly class WorkspaceTemplater
{
    /**
     * @param iterable<WorkspaceTemplateSectionInterface> $sections
     */
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        #[AutowireIterator(WorkspaceTemplateSectionInterface::TAG)]
        private iterable $sections,
    ) {
    }

    public function export(Workspace $workspace, ?WorkspaceTemplateOptions $options = null): array
    {
        $options ??= WorkspaceTemplateOptions::portable();

        $data = [];
        foreach ($this->sections as $section) {
            $data[$section::getKey()] = $section->export($workspace, $options);
        }

        return $data;
    }

    public function saveWorkspaceAsTemplate(Workspace $workspace, ?string $name = null, ?WorkspaceTemplateOptions $options = null): WorkspaceTemplate
    {
        if (!$name) {
            $name = $workspace->getName();
        }
        $wsTemplate = new WorkspaceTemplate();
        $wsTemplate->setName($name);
        $wsTemplate->setData($this->export($workspace, $options));
        $this->em->persist($wsTemplate);
        $this->em->flush();

        return $wsTemplate;
    }

    public function import(array $data, string $newName, ?string $slug, ?string $ownerId): Workspace
    {
        $this->em->beginTransaction();
        try {
            /** @var Workspace $ws */
            if (!($ws = $this->em->getRepository(Workspace::class)->findOneBy(['name' => $newName]))) {
                $this->logger->info(sprintf('Creating Workspace "%s"', $newName));
                $ws = new Workspace();
                $ws->setOwnerId($ownerId);
                $ws->setName($newName);
                $ws->setSlug($slug ?: new AsciiSlugger()->slug($newName)->toString());
            } else {
                $this->logger->info(sprintf('Updating Workspace "%s"', $newName));
            }

            $this->importToWorkspace($ws, $data, false);

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $ws;
    }

    /**
     * Template items are matched with the existing entities of the workspace by their natural key
     * (name, key, …): importing twice updates them instead of creating duplicates.
     * Instance-bound data (owners, ACEs, secret values, …) are imported if the template carries them.
     */
    public function importToWorkspace(Workspace $ws, array $data, bool $addTransaction = true): void
    {
        if ($addTransaction) {
            $this->em->beginTransaction();
        }
        try {
            // existing entities are looked up in the database
            $this->em->persist($ws);
            $this->em->flush();

            $context = new TemplateImportContext($ws, $this->em);
            foreach ($this->sections as $section) {
                $section->import($data[$section::getKey()] ?? [], $context);
            }

            $this->em->flush();
            if ($addTransaction) {
                $this->em->commit();
            }
        } catch (\Throwable $e) {
            if ($addTransaction) {
                $this->em->rollback();
            }
            throw $e;
        }
    }
}
