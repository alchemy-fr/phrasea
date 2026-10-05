<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Actions reference attribute/rendition definitions by id: they are remapped.
 * Conditions on collections keep their ids, collections are not part of a template.
 */
#[AsTaggedItem(priority: 50)]
final class AssetPolicySection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'AssetPolicy';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (AssetPolicy $item): array => [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'enabled' => $item->isEnabled(),
            'priority' => $item->getPriority(),
            'conditions' => $item->getConditions(),
            'actions' => $item->getActions(),
            ...($options->withAccessControl ? [
                'ownerId' => $item->getOwnerId(),
                'userIds' => $item->getUserIds(),
                'groupIds' => $item->getGroupIds(),
            ] : []),
        ], $this->findByWorkspace(AssetPolicy::class, $workspace, ['priority' => 'DESC', 'name' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(AssetPolicy::class, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('AssetPolicy', $item['name'], null === $o);
            if (null === $o) {
                $o = new AssetPolicy();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
                $o->setOwnerId($this->resolveOwnerId($item, $context));
            } elseif (isset($item['ownerId'])) {
                $o->setOwnerId($item['ownerId']);
            }
            $o->setEnabled($item['enabled'] ?? true);
            $o->setPriority($item['priority'] ?? 0);
            $o->setConditions($context->remapIds($item['conditions'] ?? []));
            $o->setActions($context->remapIds($item['actions'] ?? []));

            if (array_key_exists('userIds', $item) || array_key_exists('groupIds', $item)) {
                foreach ($o->getUsers()->toArray() as $user) {
                    $o->getUsers()->removeElement($user);
                }
                foreach ([
                    AssetPolicyUser::TYPE_USER => $item['userIds'] ?? [],
                    AssetPolicyUser::TYPE_GROUP => $item['groupIds'] ?? [],
                ] as $userType => $userIds) {
                    foreach ($userIds as $userId) {
                        $u = new AssetPolicyUser();
                        $u->setPolicy($o);
                        $u->setUserType($userType);
                        $u->setUserId($userId);
                        $o->getUsers()->add($u);
                    }
                }
            }
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
        }
    }
}
