<?php

declare(strict_types=1);

namespace App\Attribute;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Consumer\Handler\Asset\AttributeChanged;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AssetVoter;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly AttributeManager $attributeManager,
        private readonly DeferredIndexListener $deferredIndexListener,
        private readonly AttributeTypeRegistry $typeRegistry,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function validate(string $workspaceId, ?array $assetsId, AssetAttributeBatchUpdateInput $input): void
    {
        $validationContext = new ExecutionContext(
            $this->validator,
            'actions',
            $this->translator,
        );

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

        foreach ($assets as $asset) {
            if (!$this->security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset)) {
                throw new AccessDeniedHttpException(sprintf('Unauthorized to edit asset "%s"', $asset->getId()));
            }
        }

        foreach ($input->actions as $i => $action) {
            if ($action->definitionId) {
                if ('tags' !== $action->definitionId) {
                    $definition = $this->getAttributeDefinition($workspaceId, $action->definitionId);

                    if ($action->value) {
                        $type = $this->typeRegistry->getStrictType($definition->getFieldType());
                        $type->validate($action->value, $validationContext);
                    }

                    $this->denyUnlessGranted($definition);
                }
            } elseif ($action->name) {
                $definition = $this->getAttributeDefinitionBySlug($workspaceId, $action->name);
                $this->denyUnlessGranted($definition);
            }

            if ($action->id) {
                try {
                    $attribute = $this->em->find(Attribute::class, $action->id);
                    if (!$attribute instanceof Attribute) {
                        throw new BadRequestHttpException(sprintf('Attribute "%s" not found in action #%d', $action->id, $i));
                    }
                    $this->denyUnlessGranted($attribute->getDefinition());
                } catch (ConversionException $e) {
                    throw new BadRequestHttpException(sprintf('Invalid attribute ID "%s" in action #%d', $action->id, $i), $e);
                }
            }
        }

        if ($validationContext->getViolations()->count() > 0) {
            throw new ValidationException($validationContext->getViolations());
        }
    }

    private function denyUnlessGranted(AttributeDefinition $definition): void
    {
        if (!$definition->getClass()->isEditable()
            && !$this->security->isGranted(PermissionInterface::EDIT, $definition->getClass())) {
            throw new AccessDeniedHttpException(sprintf('Unauthorized to edit attribute definition %s', $definition->getId()));
        }
    }

    public function handleBatch(
        string $workspaceId,
        ?array $assetsId,
        AssetAttributeBatchUpdateInput $input,
        ?JwtUser $user,
        bool $dispatchUpdateEvent = false,
    ): void {
        DeferredIndexListener::disable();
        $assetsId ??= [];

        try {
            $this->em->wrapInTransaction(function () use ($user, $input, $assetsId, $workspaceId, $dispatchUpdateEvent): void {
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
                        if ('tags' === $action->definitionId) {
                            $this->handleTagAction($action, $ids);

                            continue;
                        }
                        $definition = $this->getAttributeDefinition($workspaceId, $action->definitionId);
                    } elseif ($action->name) {
                        $definition = $this->getAttributeDefinitionBySlug($workspaceId, $action->name);
                    } else {
                        $definition = null;
                    }

                    if ($definition) {
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

                            $this->upsertAttribute(null, $ids, $definition, $action);
                            break;
                        case self::ACTION_DELETE:
                            if (!$definition) {
                                throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                            }
                            $this->deleteAttributes($ids, $definition, $user, [
                                'id' => $action->id,
                                'ids' => $action->ids,
                                'origin' => $action->origin,
                                'originVendor' => $action->originVendor,
                            ]);
                            break;
                        case self::ACTION_SET:
                            if ($action->id) {
                                try {
                                    $attribute = $this->em->find(Attribute::class, $action->id);
                                    if (!$attribute instanceof Attribute) {
                                        throw new BadRequestHttpException(sprintf('Attribute "%s" not found in action #%d', $action->id, $i));
                                    }
                                    $this->upsertAttribute($attribute, $ids, $definition, $action);
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

                                    $this->deleteAttributes($ids, $definition, $user);
                                    foreach ($action->value as $value) {
                                        $vAction = clone $action;
                                        $vAction->value = $value;
                                        $this->upsertAttribute(null, $ids, $definition, $vAction);
                                    }
                                } else {
                                    foreach ($ids as $assetId) {
                                        $attribute = $this->em->getRepository(Attribute::class)->findOneBy([
                                            'definition' => $definition->getId(),
                                            'asset' => $assetId,
                                            'locale' => $action->locale,
                                        ]);
                                        $this->upsertAttribute($attribute, [$assetId], $definition, $action);
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
                                    ->select('ad.id')
                                    ->from(AttributeDefinition::class, 'ad')
                                    ->andWhere('ad.workspace = :ws')
                                    ->setParameter('ws', $workspaceId);
                                if ($user instanceof JwtUser) {
                                    $sub
                                        ->innerJoin('ad.class', 'ac')
                                        ->andWhere('ac.public = true OR ace.id IS NOT NULL');
                                    $this->joinUserAcl($sub, $user);
                                }

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
                            $user?->getId(),
                        ));
                    }
                }

                $this->em->flush();
            });
        } finally {
            DeferredIndexListener::enable();
        }
    }

    private function handleTagAction(AttributeActionInput $action, array $assetIds): void
    {
        $assetMeta = $this->em->getClassMetadata(Asset::class);
        $tagMapping = $assetMeta->getAssociationMapping('tags');
        $joinTable = $tagMapping['joinTable'];
        $assetTable = $assetMeta->getTableName();
        $tagAssociationTable = $joinTable['name'];
        $assetIdCol = $joinTable['joinColumns'][0]['name'];
        $tagIdCol = $joinTable['inverseJoinColumns'][0]['name'];

        switch ($action->action) {
            case self::ACTION_ADD:
                $query = sprintf(
                    'INSERT INTO %1$s (%2$s, %3$s) SELECT :tag, a.id FROM %4$s a WHERE a.id IN (:ids) ON CONFLICT DO NOTHING',
                    $tagAssociationTable,
                    $tagIdCol,
                    $assetIdCol,
                    $assetTable,
                );
                $this->em->getConnection()->executeQuery($query, [
                    'tag' => $action->value,
                    'ids' => $assetIds,
                ], [
                    'tag' => ParameterType::STRING,
                    'ids' => ArrayParameterType::STRING,
                ]);
                break;
            case self::ACTION_DELETE:
                $this->em->getConnection()->executeQuery(sprintf(
                    'DELETE FROM %1$s WHERE %3$s IN (:ids) AND %2$s IN (:tags)',
                    $tagAssociationTable,
                    $tagIdCol,
                    $assetIdCol,
                ), [
                    'tags' => $action->ids,
                    'ids' => $assetIds,
                ], [
                    'tags' => ArrayParameterType::STRING,
                    'ids' => ArrayParameterType::STRING,
                ]);
        }
    }

    private function upsertAttribute(
        ?Attribute $attribute,
        array $assetsId,
        AttributeDefinition $definition,
        AttributeActionInput $action,
    ): void {
        if (null !== $attribute && count($assetsId) > 1) {
            throw new \InvalidArgumentException('Attribute update is provided with many assets ID');
        }

        foreach ($assetsId as $assetId) {
            if (null === $attribute) {
                $attribute = new Attribute();
                $attribute->setAsset($this->em->getReference(Asset::class, $assetId));
                $attribute->setDefinition($definition);
            }

            try {
                $this->attributeAssigner->assignAttributeFromInput($attribute, $action);
                $this->em->persist($attribute);
            } catch (InvalidAttributeValueException) {
                // Ignore invalid values
            }

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
        return $this->attributeManager->getAttributeDefinitionBySlug($workspaceId, $slug)
            ?? throw new BadRequestHttpException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $slug, $workspaceId));
    }

    private function deleteAttributes(
        array $assetsId,
        ?AttributeDefinition $definition,
        ?JwtUser $user,
        array $options = [],
    ): void {
        $qb = $this->em->createQueryBuilder()
            ->delete()
            ->from(Attribute::class, 'a')
            ->andWhere('a.asset IN (:assets)')
            ->setParameter('assets', $assetsId);
        if ($definition) {
            $qb
                ->andWhere('a.definition = :def')
                ->setParameter('def', $definition->getId());
        } else {
            if ($user instanceof JwtUser) {
                $qb
                    ->innerJoin('a.definition', 'ad')
                    ->innerJoin('ad.class', 'ac')
                    ->andWhere('ac.public = true OR ace.id IS NOT NULL');
                $this->joinUserAcl($qb, $user);
            }
        }
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
        $qb->getQuery()->execute();
    }

    private function joinUserAcl(QueryBuilder $queryBuilder, JwtUser $user): void
    {
        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $user->getId(),
            $user->getGroups(),
            'attribute_class',
            'ac',
            PermissionInterface::EDIT,
            false
        );
    }
}
