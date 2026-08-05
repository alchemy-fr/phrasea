<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;

readonly class WorkspaceDuplicateManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function duplicateWorkspace(Workspace $workspace, string $newSlug): Workspace
    {
        $newWorkspace = new Workspace();
        $newWorkspace->setSlug($newSlug);
        $newWorkspace->setName($workspace->getName());
        $newWorkspace->setOwnerId($workspace->getOwnerId());
        $newWorkspace->setConfig($workspace->getConfig());
        $newWorkspace->setEnabledLocales($workspace->getEnabledLocales());

        $this->copyIntegrations($workspace, $newWorkspace);
        $this->copyRenditionDefinitions($workspace, $newWorkspace);
        $this->copyTags($workspace, $newWorkspace);

        $this->em->persist($newWorkspace);

        return $newWorkspace;
    }

    private function copyRenditionDefinitions(Workspace $from, Workspace $to): void
    {
        /** @var RenditionPolicy[] $items */
        $items = $this->em->getRepository(RenditionPolicy::class)->findBy([
            'workspace' => $from->getId(),
        ]);
        $classMap = [];
        foreach ($items as $item) {
            $i = new RenditionPolicy();
            $i->setName($item->getName());
            $i->setWorkspace($to);
            $this->em->persist($i);
            $classMap[$item->getId()] = $i;
        }

        /** @var RenditionDefinition[] $items */
        $items = $this->em->getRepository(RenditionDefinition::class)->findBy([
            'workspace' => $from->getId(),
        ]);
        foreach ($items as $item) {
            $i = new RenditionDefinition();
            $i->setName($item->getName());
            $i->setWorkspace($to);
            $i->setPolicy($classMap[$item->getPolicy()->getId()]);
            $i->setPriority($item->getPriority());
            $i->setKey($item->getKey());
            $i->setUseAsMain($item->isUseAsMain());
            $i->setUseAsPreview($item->isUseAsPreview());
            $i->setUseAsThumbnail($item->isUseAsThumbnail());
            $i->setUseAsAnimatedThumbnail($item->isUseAsAnimatedThumbnail());
            $i->setDefinition($item->getDefinition());
            $this->em->persist($i);
        }
    }

    private function copyTags(Workspace $from, Workspace $to): void
    {
        /** @var Tag[] $items */
        $items = $this->em->getRepository(Tag::class)->findBy([
            'workspace' => $from->getId(),
        ]);
        foreach ($items as $item) {
            $i = new Tag();
            $i->setWorkspace($to);
            $i->setName($item->getName());
            $i->setLocale($item->getLocale());
            $this->em->persist($i);
        }

        /** @var AttributeFilterRule[] $items */
        $items = $this->em->getRepository(AttributeFilterRule::class)->findBy([
            'workspace' => $from->getId(),
        ]);

        // Entity UUIDs embedded in the AQL condition (e.g. @tag references) are NOT
        // remapped to the duplicated workspace's entities: such rules fail closed.
        foreach ($items as $item) {
            $i = new AttributeFilterRule();
            $i->setCondition($item->getCondition());
            $i->setWorkspace($to);
            $i->setTargets($item->getUserIds(), $item->getGroupIds());
            $this->em->persist($i);
        }
    }

    private function copyIntegrations(Workspace $from, Workspace $to): void
    {
        /** @var WorkspaceIntegration[] $items */
        $items = $this->em->getRepository(WorkspaceIntegration::class)->findBy([
            'workspace' => $from->getId(),
        ]);
        foreach ($items as $item) {
            $i = new WorkspaceIntegration();
            $i->setName($item->getName());
            $i->setIntegration($item->getIntegration());
            $i->setEnabled($item->isEnabled());
            $i->setConfig($item->getConfig());
            $i->setWorkspace($to);
            $this->em->persist($i);
        }
    }
}
