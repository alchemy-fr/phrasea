<?php

declare(strict_types=1);

namespace App\Workspace;

use App\Entity\Core\AttributeClass;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Template\WorkspaceTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class WorkspaceTemplater
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function export(Workspace $workspace): array
    {
        return [
            'Workspace' => $this->exportWorkspace($workspace),
            'RenditionClass' => $this->exportRenditionClass($workspace->getId()),
            'RenditionDefinition' => $this->exportRenditionDefinition($workspace->getId()),
            'AttributeClass' => $this->exportAttributeClass($workspace->getId()),
            'AttributeDefinition' => $this->exportAttributeDefinition($workspace->getId()),
            'Tag' => $this->exportTag($workspace->getId()),
        ];
    }

    public function saveWorkspaceAsTemplate(Workspace $workspace, ?string $name = null): WorkspaceTemplate
    {
        if(!$name) {
            $name = $workspace->getName();
        }
        $wsTemplate = new WorkspaceTemplate();
        $wsTemplate->setName($name);
        $wsTemplate->setData($this->export($workspace));
        $this->em->persist($wsTemplate);
        $this->em->flush();

        return $wsTemplate;
    }

    public function import(array $data, string $newName, ?string $slug, ?string $ownerId): void
    {
        $this->em->beginTransaction();
        try {
            /** @var Workspace $ws */
            if (!($ws = $this->em->getRepository(Workspace::class)->findOneBy(['name' => $newName]))) {
                $this->logger->info(sprintf('Creating Workspace "%s"', $newName));
                $ws = new Workspace();
                $ws->setOwnerId($ownerId);
                $ws->setName($newName);
                $ws->setSlug($slug ?: (new AsciiSlugger())->slug($newName)->toString());
            } else {
                $this->logger->info(sprintf('Updating Workspace "%s"', $newName));
            }

            $this->importToWorkspace($ws, $data, false);

            $this->em->commit();

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function importToWorkspace(Workspace $ws, array $data, bool $addTransaction = true): void
    {
        if($addTransaction) {
            $this->em->beginTransaction();
        }
        try {
            $this->importWorkspace($ws, $data['Workspace']??[]);

            $attributeClassMap = [];
            $this->importAttributeClass($ws, $data['AttributeClass']??[], $attributeClassMap);
            $this->importAttributeDefinition($ws, $data['AttributeDefinition']??[], $attributeClassMap);

            $renditionClassMap = [];
            $this->importRenditionClass($ws, $data['RenditionClass']??[], $renditionClassMap);
            $this->importRenditionDefinition($ws, $data['RenditionDefinition']??[], $renditionClassMap);

            $this->importTag($ws, $data['Tag']??[]);

            $this->em->flush();
            if($addTransaction) {
                $this->em->commit();
            }
        } catch (\Throwable $e) {
            if($addTransaction) {
                $this->em->rollback();
            }
            throw $e;
        }
    }

    private function exportWorkspace(Workspace $workspace): array
    {
        return [
            'public' => $workspace->isPublic(),
            'enabledLocales' => $workspace->getEnabledLocales(),
            'localeFallbacks' => $workspace->getLocaleFallbacks(),
        ];
    }

    private function importWorkspace(Workspace $ws, array $data): void
    {
        if(array_key_exists('public', $data)) {
            $ws->setPublic($data['public']);
        }
        if(array_key_exists('enabledLocales', $data)) {
            $ws->setEnabledLocales($data['enabledLocales']);
        }
        if(array_key_exists('localeFallbacks', $data)) {
            $ws->setLocaleFallbacks($data['localeFallbacks']);
        }
        $this->em->persist($ws);
    }

    private function exportRenditionClass(string $workspaceId): array
    {
        $o = [];

        /** @var RenditionClass[] $items */
        $items = $this->em->getRepository(RenditionClass::class)->findBy([
            'workspace' => $workspaceId,
        ]);
        foreach ($items as $item) {
            $o[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'public' => $item->isPublic(),
                'labels' => $item->getLabels(),
            ];
        }

        return $o;
    }

    private function importRenditionClass(Workspace $ws, array $data, array &$renditionClassMap): void
    {
        foreach ($data as $item) {
            /** @var RenditionClass $o */
            if (!($o = $this->em->getRepository(RenditionClass::class)->findOneBy([
                'workspace' => $ws,
                'name' => $item['name'],
            ]))) {
                $this->logger->info(sprintf('Creating RenditionClass "%s"', $item['name']));
                $o = new RenditionClass();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
                $this->em->persist($o);
            } else {
                $this->logger->info(sprintf('Updating RenditionClass "%s"', $item['name']));
            }
            $o->setPublic($item['public']);
            $o->setLabels($item['labels']);
            $this->em->persist($o);

            $renditionClassMap[$item['id']] = $o;
        }
    }

    private function exportRenditionDefinition(string $workspaceId): array
    {
        $o = [];

        /** @var RenditionDefinition[] $items */
        $items = $this->em->getRepository(RenditionDefinition::class)->findBy([
            'workspace' => $workspaceId,
        ]);
        foreach ($items as $item) {
            $o[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'class' => $item->getClass()->getId(),
                'parent' => $item->getParent()?->getId(),
                'buildMode' => $item->getBuildMode(),
                'priority' => $item->getPriority(),
                'download' => $item->isDownload(),
                'substituable' => $item->isSubstitutable(),
                'useAsOriginal' => $item->isUseAsOriginal(),
                'useAsPreview' => $item->isUseAsPreview(),
                'useAsThumbnail' => $item->isUseAsThumbnail(),
                'useAsThumbnailActive' => $item->isUseAsThumbnailActive(),
                'labels' => $item->getLabels(),
                'definition' => $item->getDefinition(),
            ];
        }

        return $o;
    }

    private function orderByParent(array $u, array $o = []): array
    {
        $end = true;
        $tu = array_filter(
            $u,
            function($x) use(&$o, &$end) {
                return ($x['parent'] && !array_key_exists($x['parent'], $o)) || ($end = is_null($o[$x['id']] = $x));
            }
        );

        return $end || empty($tu) ? $o : $this->orderByParent($tu, $o);
    }

    private function importRenditionDefinition(Workspace $ws, array $data, array $renditionClassMap): void
    {
        $rdOrdered = $this->orderByParent($data);

        $rdMap = [];
        foreach ($rdOrdered as $id => $item) {
            /** @var RenditionDefinition $o */
            if (!($o = $this->em->getRepository(RenditionDefinition::class)->findOneBy([
                'workspace' => $ws,
                'name' => $item['name'],
            ]))) {
                $this->logger->info(sprintf('Creating RenditionDefinition "%s"', $item['name']));
                $o = new RenditionDefinition();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            } else {
                $this->logger->info(sprintf('Updating RenditionDefinition "%s"', $item['name']));
            }

            $o->setBuildMode($item['buildMode']);
            $o->setPriority($item['priority']);
            $o->setDownload($item['download']);
            $o->setSubstitutable($item['substituable']);
            $o->setUseAsOriginal($item['useAsOriginal']);
            $o->setUseAsPreview($item['useAsPreview']);
            $o->setUseAsThumbnail($item['useAsThumbnail']);
            $o->setUseAsThumbnailActive($item['useAsThumbnailActive']);
            $o->setLabels($item['labels']);
            $o->setDefinition($item['definition']);
            $o->setClass($renditionClassMap[$item['class']]);
            if ($item['parent']) {
                $o->setParent($rdMap[$item['parent']]);
            }
            $rdMap[$id] = $o;

            $this->em->persist($o);
        }
    }

    private function exportAttributeClass(string $workspaceId): array
    {
        $o = [];

        /** @var AttributeClass[] $items */
        $items = $this->em->getRepository(AttributeClass::class)->findBy([
            'workspace' => $workspaceId,
        ]);
        foreach ($items as $item) {
            $o[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'editable' => $item->isEditable(),
                'public' => $item->isPublic(),
                'labels' => $item->getLabels(),
            ];
        }

        return $o;
    }

    private function importAttributeClass(Workspace $ws, array $data, array &$attributeClassMap): void
    {
        foreach ($data as $item) {
            /** @var AttributeClass $o */
            if (!($o = $this->em->getRepository(AttributeClass::class)->findOneBy([
                'workspace' => $ws,
                'name' => $item['name'],
            ]))) {
                $this->logger->info(sprintf('Creating AttributeClass "%s"', $item['name']));
                $o = new AttributeClass();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            } else {
                $this->logger->info(sprintf('Updating AttributeClass "%s"', $item['name']));
            }
            $o->setPublic($item['public']);
            $o->setLabels($item['labels']);
            $o->setEditable($item['editable']);
            $this->em->persist($o);

            $attributeClassMap[$item['id']] = $o;
        }
    }

    private function exportAttributeDefinition(string $workspaceId): array
    {
        $o = [];

        /** @var AttributeDefinition[] $items */
        $items = $this->em->getRepository(AttributeDefinition::class)->findBy([
            'workspace' => $workspaceId,
        ]);
        foreach ($items as $item) {
            $o[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'class' => $item->getClass()->getId(),
                'labels' => $item->getLabels(),
                'entityType' => $item->getEntityType(),
                'fallback' => $item->getFallback(),
                'fieldType' => $item->getFieldType(),
                'fileType' => $item->getFileType(),
                'initialValues' => $item->getInitialValues(),
                'position' => $item->getPosition(),
                'searchBoost' => $item->getSearchBoost(),
                'allowInvalid' => $item->isAllowInvalid(),
                'facetEnabled' => $item->isFacetEnabled(),
                'multiple' => $item->isMultiple(),
                'searchable' => $item->isSearchable(),
                'sortable' => $item->isSortable(),
                'suggest' => $item->isSuggest(),
                'translatable' => $item->isTranslatable(),
            ];
        }

        return $o;
    }

    private function importAttributeDefinition(Workspace $ws, array $data, array $attributeClassMap): void
    {
        foreach ($data as $item) {
            /** @var AttributeDefinition $o */
            if (!($o = $this->em->getRepository(AttributeDefinition::class)->findOneBy([
                'workspace' => $ws,
                'name' => $item['name'],
            ]))) {
                $this->logger->info(sprintf('Creating AttributeDefinition "%s"', $item['name']));
                $o = new AttributeDefinition();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            } else {
                $this->logger->info(sprintf('Updating AttributeDefinition "%s"', $item['name']));
            }
            $o->setClass($attributeClassMap[$item['class']]);
            $o->setLabels($item['labels']);
            $o->setEntityType($item['entityType']);
            $o->setFallback($item['fallback']);
            $o->setFieldType($item['fieldType']);
            $o->setFileType($item['fileType']);
            $o->setInitialValues($item['initialValues']);
            $o->setPosition($item['position']);
            $o->setSearchBoost($item['searchBoost']);
            $o->setAllowInvalid($item['allowInvalid']);
            $o->setFacetEnabled($item['facetEnabled']);
            $o->setMultiple($item['multiple']);
            $o->setSearchable($item['searchable']);
            $o->setSortable($item['sortable']);
            $o->setSuggest($item['suggest']);
            $o->setTranslatable($item['translatable']);
            $this->em->persist($o);
        }
    }

    private function exportTag(string $workspaceId): array
    {
        $o = [];

        /** @var Tag[] $items */
        $items = $this->em->getRepository(Tag::class)->findBy([
            'workspace' => $workspaceId,
        ]);
        foreach ($items as $item) {
            $o[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'color' => $item->getColor(),
                'translations' => $item->getTranslations(),
                'locale' => $item->getLocale(),
            ];
        }

        return $o;
    }

    private function importTag(Workspace $ws, array $data): void
    {
        foreach ($data as $item) {
            /** @var Tag $o */
            if (!($o = $this->em->getRepository(Tag::class)->findOneBy([
                'workspace' => $ws,
                'name' => $item['name'],
            ]))) {
                $this->logger->info(sprintf('Creating Tag "%s"', $item['name']));
                $o = new Tag();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            } else {
                $this->logger->info(sprintf('Updating Tag "%s"', $item['name']));
            }
            $o->setColor($item['color']);
            $o->setTranslations($item['translations']);
            $o->setLocale($item['locale']);
            $this->em->persist($o);
        }
    }
}
