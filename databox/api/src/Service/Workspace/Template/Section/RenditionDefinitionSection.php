<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Templates exported before the ids were UUIDs identify the definitions by "#name".
 */
#[AsTaggedItem(priority: 120)]
final class RenditionDefinitionSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'RenditionDefinition';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        $o = array_map(fn (RenditionDefinition $item): array => [
            'id' => $item->getId(),
            'key' => $item->getKey(),
            'name' => $item->getName(),
            'policy' => $item->getPolicy()->getId(),
            'parent' => $item->getParent()?->getId(),
            'target' => $item->getTarget()->value,
            'buildMode' => $item->getBuildMode(),
            'priority' => $item->getPriority(),
            'download' => $item->isDownload(),
            'substituable' => $item->isSubstitutable(),
            'writeMetadata' => $item->isWriteMetadata(),
            'metadata' => $item->getMetadata() ?: null,
            'useAsMain' => $item->isUseAsMain(),
            'useAsPreview' => $item->isUseAsPreview(),
            'useAsThumbnail' => $item->isUseAsThumbnail(),
            'useAsAnimatedThumbnail' => $item->isUseAsAnimatedThumbnail(),
            'labels' => $item->getLabels(),
            'translations' => $item->getTranslations(),
            'definition' => $item->getDefinition(),
        ], $this->findByWorkspace(RenditionDefinition::class, $workspace, ['priority' => 'DESC', 'name' => 'ASC']));

        return array_values($this->orderByParent($o));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($this->orderByParent($data) as $id => $item) {
            $o = $context->findExisting(RenditionDefinition::class, isset($item['key']) ? [
                'workspace' => $ws,
                'key' => $item['key'],
            ] : null, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('RenditionDefinition', $item['name'], null === $o);
            if (null === $o) {
                $o = new RenditionDefinition();
                $o->setWorkspace($ws);
            }

            $o->setName($item['name']);
            if (array_key_exists('key', $item)) {
                $o->setKey($item['key']);
            }
            if (array_key_exists('target', $item)) {
                $o->setTarget(AssetTypeEnum::tryFrom((int) $item['target']) ?? AssetTypeEnum::Asset);
            }
            $o->setBuildMode($item['buildMode']);
            $o->setPriority($item['priority']);
            $o->setDownload($item['download']);
            $o->setSubstitutable($item['substituable']);
            $o->setWriteMetadata($item['writeMetadata'] ?? false);
            $o->setMetadata($item['metadata'] ?? null);
            $o->setUseAsMain($item['useAsMain']);
            $o->setUseAsPreview($item['useAsPreview']);
            $o->setUseAsThumbnail($item['useAsThumbnail']);
            $o->setUseAsAnimatedThumbnail($item['useAsAnimatedThumbnail']);
            $o->setLabels($item['labels']);
            if (array_key_exists('translations', $item)) {
                $o->setTranslations($item['translations']);
            }
            $o->setDefinition($item['definition']);

            $policy = $context->get(RenditionPolicySection::getKey(), $item['policy'], RenditionPolicy::class);
            if (null === $policy) {
                throw new \InvalidArgumentException(sprintf('Unknown RenditionPolicy "%s" for RenditionDefinition "%s"', $item['policy'], $item['name']));
            }
            $o->setPolicy($policy);
            $o->setParent($item['parent'] ? $context->get(self::getKey(), $item['parent'], RenditionDefinition::class) : null);

            $this->em->persist($o);
            $context->register(self::getKey(), (string) $id, $o);
        }
    }

    /**
     * Orders the items so that a parent always comes before its children, keyed by id.
     */
    private function orderByParent(array $u, array $o = []): array
    {
        $end = true;
        $tu = array_filter(
            $u,
            function ($x) use (&$o, &$end) {
                return ($x['parent'] && !array_key_exists((string) $x['parent'], $o)) || ($end = is_null($o[$x['id']] = $x));
            }
        );

        return $end || empty($tu) ? $o : $this->orderByParent($tu, $o);
    }
}
