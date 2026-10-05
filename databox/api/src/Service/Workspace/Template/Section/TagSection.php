<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 100)]
final class TagSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'Tag';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (Tag $item): array => [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'color' => $item->getColor(),
            'translations' => $item->getTranslations(),
            'locale' => $item->getLocale(),
        ], $this->findByWorkspace(Tag::class, $workspace, ['name' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(Tag::class, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('Tag', $item['name'], null === $o);
            if (null === $o) {
                $o = new Tag();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            }
            $o->setColor($item['color']);
            $o->setTranslations($item['translations']);
            $o->setLocale($item['locale']);
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
        }
    }
}
