<?php

declare(strict_types=1);

namespace App\Workspace;

use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionRule;
use App\Entity\Core\Tag;
use App\Entity\Core\TagFilterRule;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;

class WorkspaceDuplicateManager
{
    public function __construct(private readonly EntityManagerInterface $em)
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
        /** @var RenditionClass[] $items */
        $items = $this->em->getRepository(RenditionClass::class)->findBy([
            'workspace' => $from->getId(),
        ]);
        $classMap = [];
        foreach ($items as $item) {
            $i = new RenditionClass();
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
            $i->setClass($classMap[$item->getClass()->getId()]);
            $i->setPriority($item->getPriority());
            $i->setUseAsOriginal($item->isUseAsOriginal());
            $i->setUseAsPreview($item->isUseAsPreview());
            $i->setUseAsThumbnail($item->isUseAsThumbnail());
            $i->setUseAsThumbnailActive($item->isUseAsThumbnailActive());
            $i->setDefinition($item->getDefinition());
            $this->em->persist($i);
        }
        /** @var RenditionRule[] $items */
        $items = $this->em->getRepository(RenditionRule::class)->findBy([
            'objectType' => RenditionRule::TYPE_WORKSPACE,
            'objectId' => $from->getId(),
        ]);

        $replace = fn(RenditionClass $class): RenditionClass => $classMap[$class->getId()];
        foreach ($items as $item) {
            $i = new RenditionRule();
            $i->setObjectType(RenditionRule::TYPE_WORKSPACE);
            $i->setObjectId($to->getId());
            $i->setUserType($item->getUserType());
            $i->setUserId($item->getUserId());
            $i->setAllowed($item->getAllowed()->map($replace));
            $this->em->persist($i);
        }
    }

    private function copyTags(Workspace $from, Workspace $to): void
    {
        /** @var Tag[] $items */
        $items = $this->em->getRepository(Tag::class)->findBy([
            'workspace' => $from->getId(),
        ]);
        $map = [];
        foreach ($items as $item) {
            $i = new Tag();
            $i->setWorkspace($to);
            $i->setName($item->getName());
            $i->setLocale($item->getLocale());
            $this->em->persist($i);
            $map[$item->getId()] = $i;
        }

        /** @var TagFilterRule[] $items */
        $items = $this->em->getRepository(TagFilterRule::class)->findBy([
            'objectType' => TagFilterRule::TYPE_WORKSPACE,
            'objectId' => $from->getId(),
        ]);

        $replace = fn(Tag $t): Tag => $map[$t->getId()];
        foreach ($items as $item) {
            $i = new TagFilterRule();
            $i->setExclude($item->getExclude()->map($replace));
            $i->setInclude($item->getInclude()->map($replace));
            $i->setObjectId($to->getId());
            $i->setObjectType(TagFilterRule::TYPE_WORKSPACE);
            $i->setUserId($item->getUserId());
            $i->setUserType($item->getUserType());
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
            $i->setTitle($item->getTitle());
            $i->setIntegration($item->getIntegration());
            $i->setEnabled($item->isEnabled());
            $i->setConfig($item->getConfig());
            $i->setWorkspace($to);
            $this->em->persist($i);
        }
    }
}
