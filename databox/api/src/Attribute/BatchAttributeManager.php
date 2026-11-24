<?php

declare(strict_types=1);

namespace App\Attribute;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtInterface;
use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Util\LocaleUtil;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\Hierarchy\HierarchyAttributeFiller;
use App\Consumer\Handler\Asset\AttributeChanged;
use App\Doctrine\Listener\HierarchyAttributeListener;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Security\AssetAttributeFilterChecker;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\AttributeDefinitionVoter;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BatchAttributeManager
{
    final public const string ACTION_SET = 'set';
    final public const string ACTION_DELETE = 'delete';
    final public const string ACTION_REPLACE = 'replace';
    final public const string ACTION_ADD = 'add';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeAssigner $attributeAssigner,
        private readonly Security $security,
        private readonly PostFlushStack $postFlushStack,
        private readonly DeferredIndexListener $deferredIndexListener,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributeValidator $attributeValidator,
        private readonly HierarchyAttributeFiller $hierarchyAttributeFiller,
        private readonly HierarchyAttributeListener $hierarchyAttributeListener,
        private readonly AttributeChangeAuditor $attributeChangeAuditor,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly AssetAttributeFilterChecker $attributeFilterChecker,
    ) {
    }

    public function validate(string $workspaceId, ?array $assetsId, AssetAttributeBatchUpdateInput $input): void
    {
        $allAssetIndex = [];
        foreach (($assetsId ?? []) as $id) {
            $allAssetIndex[$id] = true;
        }
        foreach ($input->actions as $action) {
            if (null !== $action->assets) {
                foreach ($action->assets as $id) {
                    $allAssetIndex[$id] = true;
                }
            }
        }

        $allAssetIds = array_keys($allAssetIndex);

        $assets = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Asset::class, 'a')
            ->andWhere('a.workspace = :w')
            ->setParameter('w', $workspaceId)
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $allAssetIds)
            ->getQuery()
            ->getResult();

        if (count($assets) !== count($allAssetIds)) {
            throw new \InvalidArgumentException('Some assets where not found. Possible issues: there are coming from different workspaces, they were deleted');
        }

        $this->attributeFilterChecker->preloadForCurrentUser($assets);

        foreach ($assets as $asset) {
            if (!$this->security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset)) {
                throw new AccessDeniedHttpException(sprintf('Unauthorized to edit asset "%s"', $asset->getId()));
            }
        }

        $this->attributeValidator->validateAttributeInputs($workspaceId, array_filter($input->actions, fn (AbstractBaseAttributeInput $input): bool => in_array($input->action, [self::ACTION_ADD, self::ACTION_SET], true)), 'actions');
    }

    public function handleBatch(
        string $workspaceId,
        ?array $assetsId,
        AssetAttributeBatchUpdateInput $input,
        ?JwtInterface $userOrClient,
        bool $dispatchUpdateEvent = false,
    ): void {
        DeferredIndexListener::disable();
        $this->hierarchyAttributeListener->disabled = true;
        $assetsId ??= [];

        // The DQL writes are invisible to the audit listener: the values are compared before and after
        $auditedAssetIds = $assetsId;
        $operations = [];
        foreach ($input->actions as $action) {
            array_push($auditedAssetIds, ...($action->assets ?? []));
            $operations[] = [
                'definitionId' => $action->definitionId,
                'action' => $action->action,
                'assets' => $action->assets,
            ];
        }
        $auditedAssetIds = array_values(array_unique($auditedAssetIds));

        try {
            $this->em->wrapInTransaction(fn () => $this->attributeChangeAuditor->audit($auditedAssetIds, $operations, function () use ($userOrClient, $input, $assetsId, $workspaceId, $dispatchUpdateEvent): void {
                $updatedAssets = [];
                $changedAttributeDefinitions = [];

                foreach ($assetsId as $id) {
                    $updatedAssets[$id] = true;
                }

                foreach ($input->actions as $i => $action) {
                    $ids = $action->assets ?? $assetsId;
                    if (null !== $action->assets) {
                        foreach ($action->assets as $id) {
                            $updatedAssets[$id] = true;
                        }
                    }

                    if ($action->definitionId) {
                        $definition = $this->getAttributeDefinition($workspaceId, $action->definitionId);
                    } elseif ($action->name) {
                        $definition = $this->getAttributeDefinitionBySlug($workspaceId, $action->name);
                    } else {
                        $definition = null;
                    }

                    if ($definition) {
                        if (!$definition->isEditable()) {
                            throw new BadRequestHttpException(sprintf('Attribute "%s" is not editable in action #%d', $definition->getSlug(), $i));
                        }
                        // validate() only checks the attribute policy of "add" and "set" actions
                        if (!$definition->getPolicy()->isEditable()
                            && !$this->security->isGranted(PermissionInterface::EDIT, $definition->getPolicy())) {
                            throw new AccessDeniedHttpException(sprintf('Unauthorized to edit attribute "%s" in action #%d', $definition->getSlug(), $i));
                        }
                        $changedAttributeDefinitions[$definition->getId()] = true;
                    } else {
                        $changedAttributeDefinitions['*'] = true;
                    }

                    switch ($action->action) {
                        case self::ACTION_ADD:
                            if (!$definition) {
                                throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                            }
                            if (!$definition->isMultiple()) {
                                throw new BadRequestHttpException(sprintf('Attribute "%s" is not multi-valued in action #%d', $definition->getName(), $i));
                            }

                            $this->upsertOrRemoveAttribute(null, $this->getAssetsWithoutValue($ids, $definition, $action), $definition, $action);
                            break;
                        case self::ACTION_DELETE:
                            if (!$definition) {
                                throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                            }
                            $this->deleteAttributes($ids, $definition, [
                                'id' => $action->id,
                                'ids' => $action->ids,
                                'values' => null !== $action->value ? $this->normalizeDeletedValues($definition, $action->value) : null,
                                'origin' => $action->origin,
                                'originVendor' => $action->originVendor,
                                'originVendorContext' => $action->originVendorContext,
                            ]);
                            break;
                        case self::ACTION_SET:
                            if ($action->id) {
                                try {
                                    $attribute = $this->em->find(Attribute::class, $action->id);
                                    // Only the attributes of the assets being edited (whose rights were checked)
                                    if (!$attribute instanceof Attribute || !in_array($attribute->getAsset()->getId(), $ids, true)) {
                                        throw new BadRequestHttpException(sprintf('Attribute "%s" not found in action #%d', $action->id, $i));
                                    }
                                    $def = $attribute->getDefinition();

                                    $this->upsertOrRemoveAttribute($attribute, $ids, $def, $action);
                                } catch (ConversionException $e) {
                                    throw new BadRequestHttpException(sprintf('Invalid attribute ID "%s" in action #%d', $action->id, $i), $e);
                                }
                            } else {
                                if (!$definition) {
                                    throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                                }

                                if ($definition->isMultiple()) {
                                    if (!is_array($action->value)) {
                                        throw new BadRequestHttpException(sprintf('Attribute "%s" is a multi-valued in action #%d, use add/delete actions for this kind of attribute or pass an array in "value"', $definition->getName(), $i));
                                    }

                                    $this->deleteAttributes($ids, $definition);
                                    foreach ($action->value as $value) {
                                        $vAction = clone $action;
                                        $vAction->value = $value;
                                        $this->upsertOrRemoveAttribute(null, $ids, $definition, $vAction);
                                    }
                                } else {
                                    foreach ($ids as $assetId) {
                                        $attribute = $this->em->getRepository(Attribute::class)->findOneBy([
                                            'definition' => $definition->getId(),
                                            'asset' => $assetId,
                                            'locale' => $action->locale,
                                        ]);
                                        $this->upsertOrRemoveAttribute($attribute, [$assetId], $definition, $action);
                                    }
                                }
                            }
                            break;
                        case self::ACTION_REPLACE:
                            $qb = $this->em->createQueryBuilder()
                                ->update();
                            if ($action->regex) {
                                if ($action->flags) {
                                    $qb
                                        ->set('a.value', 'REGEXP_REPLACE(a.value, :from, :to, :flags)')
                                        ->setParameter('flags', $action->flags);
                                } else {
                                    $qb->set('a.value', 'REGEXP_REPLACE(a.value, :from, :to)');
                                }
                            } else {
                                $qb->set('a.value', 'REPLACE(a.value, :from, :to)');
                            }
                            $qb
                                ->from(Attribute::class, 'a')
                                ->andWhere('a.asset IN (:assets)')
                                ->setParameter('assets', $ids)
                                ->setParameter('from', $action->value)
                                ->setParameter('to', $action->replaceWith);
                            if ($definition) {
                                $qb
                                    ->andWhere('a.definition = :def')
                                    ->setParameter('def', $definition->getId());
                            } else {
                                $sub = $this->em
                                    ->createQueryBuilder()
                                    ->select('DISTINCT ad.id')
                                    ->from(AttributeDefinition::class, 'ad')
                                    ->innerJoin('ad.policy', 'ap')
                                    ->andWhere('ad.workspace = :ws')
                                    ->andWhere('ad.editable = true')
                                    ->setParameter('ws', $workspaceId);
                                $this->restrictToEditablePolicies($sub, $userOrClient);

                                foreach ($sub->getParameters() as $param) {
                                    $qb->setParameter($param->getName(), $param->getValue());
                                }

                                $qb->andWhere($qb->expr()->in('a.definition', $sub->getDQL()));
                            }
                            if ($action->id) {
                                $qb
                                    ->andWhere('a.id = :id')
                                    ->setParameter('id', $action->id);
                            }
                            $qb->getQuery()->execute();
                            break;
                        default:
                            throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action->action));
                    }
                }

                $updatedAssetIds = array_keys($updatedAssets);
                $this->fillHierarchy($workspaceId, $updatedAssetIds, $changedAttributeDefinitions, $input->hierarchyConflict);

                $this->em->createQueryBuilder()
                    ->update()
                    ->from(Asset::class, 't')
                    ->set('t.attributesEditedAt', ':now')
                    ->andWhere('t.id IN (:ids)')
                    ->setParameter('now', new \DateTimeImmutable())
                    ->setParameter('ids', $updatedAssetIds)
                    ->getQuery()
                    ->execute();

                $attributes = array_keys($changedAttributeDefinitions);
                foreach ($updatedAssetIds as $assetId) {
                    // Force assets to be re-indexed
                    $this->deferredIndexListener->scheduleForUpdate($this->em->getReference(Asset::class, $assetId));

                    if ($dispatchUpdateEvent) {
                        $this->postFlushStack->addBusMessage(new AttributeChanged(
                            $attributes,
                            $assetId,
                            $userOrClient?->getId(),
                        ));
                    }
                }

                $this->em->flush();
            }));
        } finally {
            DeferredIndexListener::enable();
            $this->hierarchyAttributeListener->disabled = false;
        }
    }

    /**
     * Fills the ancestors of the written hierarchy values; throws on a conflict the strategy leaves.
     *
     * @param array<string, true> $changedAttributeDefinitions
     */
    private function fillHierarchy(string $workspaceId, array $assetIds, array $changedAttributeDefinitions, ?string $strategy): void
    {
        $definitionIds = isset($changedAttributeDefinitions['*']) ? null : array_keys($changedAttributeDefinitions);
        if (empty($assetIds) || !$this->hierarchyAttributeFiller->isConcerned($workspaceId, $definitionIds)) {
            return;
        }

        $this->em->flush();
        $this->hierarchyAttributeFiller->fill(
            $workspaceId,
            $assetIds,
            $definitionIds ?? [],
            $strategy ?? HierarchyAttributeFiller::CONFLICT_REJECT,
        );
    }

    private function upsertOrRemoveAttribute(
        ?Attribute $attribute,
        array $assetsId,
        AttributeDefinition $definition,
        AttributeActionInput $action,
    ): void {
        if (null !== $attribute && count($assetsId) > 1) {
            throw new \InvalidArgumentException('Attribute update is provided with many assets ID');
        }

        $assets = $this->em->getRepository(Asset::class)->findByIds($assetsId);
        $assets = array_filter($assets, fn (Asset $asset): bool => $asset->getWorkspaceId() === $definition->getWorkspaceId() && $definition->isTargetedForAsset($asset));

        foreach ($assets as $asset) {
            $normalizedValue = $this->attributeAssigner->normalizeValue($definition, $action->value);
            if (null === $normalizedValue) {
                if (null !== $attribute) {
                    $this->em->remove($attribute);
                }
            } else {
                if (null === $attribute) {
                    $attribute = new Attribute();
                    $attribute->setAsset($asset);
                    $attribute->setDefinition($definition);
                }

                $this->attributeAssigner->assignAttributeFromInput($attribute, $action, $normalizedValue);
                $this->em->persist($attribute);
            }

            $this->attributeAssigner->resetAssetAttributesCache($asset);

            $attribute = null;
        }
    }

    private function getAttributeDefinition(string $workspaceId, string $id): AttributeDefinition
    {
        $def = $this->em->find(AttributeDefinition::class, $id);
        if (!$def instanceof AttributeDefinition) {
            throw new BadRequestHttpException(sprintf('Attribute definition "%s" not found', $id));
        }
        if ($workspaceId !== $def->getWorkspaceId()) {
            throw new BadRequestHttpException(sprintf('Attribute definition "%s" is not in the same workspace as the asset', $id));
        }

        return $def;
    }

    private function getAttributeDefinitionBySlug(string $workspaceId, string $slug): AttributeDefinition
    {
        return $this->attributeDefinitionRepository->getAttributeDefinitionBySlug($workspaceId, $slug)
            ?? throw new BadRequestHttpException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $slug, $workspaceId));
    }

    private function deleteAttributes(
        array $assetsId,
        AttributeDefinition $definition,
        array $options = [],
    ): void {
        $qb = $this->em->createQueryBuilder()
            ->delete()
            ->from(Attribute::class, 'a')
            ->andWhere('a.asset IN (:assets)')
            ->setParameter('assets', $assetsId)
            ->andWhere('a.definition = :def')
            ->setParameter('def', $definition->getId());
        if ($options['id'] ?? null) {
            $qb
                ->andWhere('a.id = :id')
                ->setParameter('id', $options['id']);
        }
        if ($options['ids'] ?? null) {
            $qb
                ->andWhere('a.id IN (:ids)')
                ->setParameter('ids', $options['ids']);
        }
        if (null !== ($options['values'] ?? null)) {
            if (empty($options['values'])) {
                return;
            }
            $qb
                ->andWhere('a.value IN (:values)')
                ->setParameter('values', $options['values']);
        }
        if ($options['origin'] ?? null) {
            $qb
                ->andWhere('a.origin = :origin')
                ->setParameter('origin', $options['origin']);
        }
        if ($options['originVendor'] ?? null) {
            $qb
                ->andWhere('a.originVendor = :originVendor')
                ->setParameter('originVendor', $options['originVendor']);
        }
        if ($options['originVendorContext'] ?? null) {
            $qb
                ->andWhere('a.originVendorContext = :ovc')
                ->setParameter('ovc', $options['originVendorContext']);
        }
        $qb->getQuery()->execute();
    }

    /**
     * Adding a value an asset already has keeps a single one.
     *
     * @param string[] $assetIds
     *
     * @return string[]
     */
    private function getAssetsWithoutValue(array $assetIds, AttributeDefinition $definition, AttributeActionInput $action): array
    {
        if (empty($assetIds) || !is_scalar($action->value)) {
            return $assetIds;
        }
        $type = $this->attributeTypeRegistry->getStrictType($definition->getType());
        $normalized = $type->normalizeValue($action->value);
        $value = null !== $normalized ? $type->convertToDbValue($normalized) : null;
        if (null === $value) {
            return $assetIds;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('IDENTITY(a.asset)')
            ->from(Attribute::class, 'a')
            ->andWhere('a.asset IN (:assets)')
            ->andWhere('a.definition = :def')
            ->andWhere('a.value = :value')
            ->setParameter('assets', $assetIds)
            ->setParameter('def', $definition->getId())
            ->setParameter('value', $value);
        if ($action->locale) {
            $qb
                ->andWhere('a.locale = :locale')
                ->setParameter('locale', LocaleUtil::normalizeLocale($action->locale));
        } else {
            $qb->andWhere('a.locale IS NULL');
        }

        $existing = array_map(strval(...), $qb->getQuery()->getSingleColumnResult());

        return array_values(array_diff($assetIds, $existing));
    }

    /**
     * The values to remove, stored as the attribute assigner stores them.
     *
     * @return string[]
     */
    private function normalizeDeletedValues(AttributeDefinition $definition, mixed $value): array
    {
        $type = $this->attributeTypeRegistry->getStrictType($definition->getType());
        $values = [];
        foreach (is_array($value) ? $value : [$value] as $v) {
            $normalized = $type->normalizeValue($v);
            $dbValue = null !== $normalized ? $type->convertToDbValue($normalized) : null;
            if (null !== $dbValue) {
                $values[] = $dbValue;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * Restricts the query to the definitions whose policy ("ap") the user or client may edit.
     */
    private function restrictToEditablePolicies(QueryBuilder $queryBuilder, ?JwtInterface $userOrClient): void
    {
        if ($userOrClient instanceof JwtUser) {
            $queryBuilder->andWhere('(ap.public = true AND ap.editable = true) OR ace.id IS NOT NULL');
            $this->joinUserAcl($queryBuilder, $userOrClient);

            return;
        }

        if ($userOrClient instanceof JwtOauthClient && $userOrClient->hasScope(AttributeDefinitionVoter::SCOPE_PREFIX.AttributeDefinitionVoter::EDIT)) {
            return;
        }

        $queryBuilder->andWhere('ap.public = true AND ap.editable = true');
    }

    private function joinUserAcl(QueryBuilder $queryBuilder, JwtUser $user): void
    {
        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $user->getId(),
            $user->getGroups(),
            AttributePolicy::OBJECT_TYPE,
            'ap',
            PermissionInterface::EDIT,
            false
        );
    }
}
