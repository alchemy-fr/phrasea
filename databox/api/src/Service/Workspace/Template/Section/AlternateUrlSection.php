<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AlternateUrl;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 30)]
final class AlternateUrlSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'AlternateUrl';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (AlternateUrl $item): array => [
            'type' => $item->getType(),
            'label' => $item->getLabel(),
        ], $this->findByWorkspace(AlternateUrl::class, $workspace, ['type' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(AlternateUrl::class, [
                'workspace' => $ws,
                'type' => $item['type'],
            ]);
            $this->logUpsert('AlternateUrl', $item['type'], null === $o);
            if (null === $o) {
                $o = new AlternateUrl();
                $o->setWorkspace($ws);
                $o->setType($item['type']);
            }
            $o->setLabel($item['label']);
            $this->em->persist($o);

            $context->register(self::getKey(), null, $o);
        }
    }
}
