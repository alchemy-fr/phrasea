<?php

declare(strict_types=1);

namespace App\Attribute;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Elasticsearch\Listener\DeferredIndexListener;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AssetVoter;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Security;

class BatchAttributeManager
{
    final public const ACTION_SET = 'set';
    final public const ACTION_DELETE = 'delete';
    final public const ACTION_REPLACE = 'replace';
    final public const ACTION_ADD = 'add';

    public function __construct(private readonly EntityManagerInterface $em, private readonly AttributeAssigner $attributeAssigner, private readonly Security $security, private readonly DeferredIndexListener $deferredIndexListener)
    {
    }

    public function validate(array $assetsId, AssetAttributeBatchUpdateInput $input): ?string
    {
        if (empty($assetsId)) {
            return null;
        }

        $firstId = $assetsId[0];
        /** @var Asset $assetOne */
        $assetOne = $this->em->getRepository(Asset::class)->find($firstId);
        if (!$assetOne instanceof Asset) {
            throw new \InvalidArgumentException(sprintf('Asset "%s" not found', $firstId));
        }

        $workspaceId = $assetOne->getWorkspaceId();
        $assets = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Asset::class, 'a')
            ->andWhere('a.workspace = :w')
            ->setParameter('w', $workspaceId)
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $assetsId)
            ->getQuery()
            ->getResult();

        if ((is_countable($assets) ? count($assets) : 0) !== count($assetsId)) {
            throw new \InvalidArgumentException('Some assets where not found. Possible issues: there are coming from different workspaces, they were deleted');
        }

        foreach ($assets as $asset) {
            if (!$this->security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset)) {
                throw new AccessDeniedHttpException(sprintf('Unauthorized to edit asset %s', $asset->getId()));
            }
        }

        foreach ($input->actions as $i => $action) {
            if ($action->definitionId) {
                $definition = $this->getAttributeDefinition($workspaceId, $action->definitionId);
                $this->denyUnlessGranted($definition);
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

        return $workspaceId;
    }

    private function denyUnlessGranted(AttributeDefinition $definition): void
    {
        if (!$definition->getClass()->isEditable()
            && !$this->security->isGranted(PermissionInterface::EDIT, $definition->getClass())) {
            throw new AccessDeniedHttpException(sprintf('Unauthorized to edit attribute definition %s', $definition->getId()));
        }
    }

    public function handleBatch(string $workspaceId, array $assetsId, AssetAttributeBatchUpdateInput $input, ?RemoteUser $user): void
    {
        if (empty($assetsId)) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($user, $input, $assetsId, $workspaceId): void {
            foreach ($input->actions as $i => $action) {
                if ($action->definitionId) {
                    $definition = $this->getAttributeDefinition($workspaceId, $action->definitionId);
                } elseif ($action->name) {
                    $definition = $this->getAttributeDefinitionBySlug($workspaceId, $action->name);
                } else {
                    $definition = null;
                }

                switch ($action->action) {
                    case self::ACTION_ADD:
                        if (!$definition) {
                            throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                        }
                        if (!$definition->isMultiple()) {
                            throw new BadRequestHttpException(sprintf('Attribute "%s" is not multi-valued in action #%d', $definition->getName(), $i));
                        }

                        $this->upsertAttribute(null, $assetsId, $definition, $action);
                        break;
                    case self::ACTION_DELETE:
                        if (!$definition) {
                            throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                        }
                        $this->deleteAttributes($assetsId, $definition, $user, [
                            'id' => $action->id,
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
                                $this->upsertAttribute($attribute, $assetsId, $definition, $action);
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

                                $this->deleteAttributes($assetsId, $definition, $user);
                                foreach ($action->value as $value) {
                                    $vAction = clone $action;
                                    $vAction->value = $value;
                                    $this->upsertAttribute(null, $assetsId, $definition, $vAction);
                                }
                            } else {
                                foreach ($assetsId as $assetId) {
                                    $attribute = $this->em->getRepository(Attribute::class)->findOneBy([
                                        'definition' => $definition->getId(),
                                        'asset' => $assetId,
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
                                    ->setParameter('flags', $action->flags)
                                ;
                            } else {
                                $qb->set('a.value', 'REGEXP_REPLACE(a.value, :from, :to)');
                            }
                        } else {
                            $qb->set('a.value', 'REPLACE(a.value, :from, :to)');
                        }
                        $qb
                            ->from(Attribute::class, 'a')
                            ->andWhere('a.asset IN (:assets)')
                            ->setParameter('assets', $assetsId)
                            ->setParameter('from', $action->value)
                            ->setParameter('to', $action->replaceWith)
                        ;
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
                                ->setParameter('ws', $workspaceId)
                            ;
                            if ($user instanceof RemoteUser) {
                                $sub
                                    ->innerJoin('ad.class', 'ac')
                                    ->andWhere('ac.public = true OR ace.id IS NOT NULL')
                                ;
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

            $this->em->createQueryBuilder()
                ->update()
                ->from(Asset::class, 't')
                ->set('t.attributesEditedAt', ':now')
                ->andWhere('t.id IN (:ids)')
                ->setParameter('now', new \DateTimeImmutable())
                ->setParameter('ids', $assetsId)
                ->getQuery()
                ->execute()
            ;

            // Force assets to be re-indexed on terminate
            foreach ($assetsId as $assetId) {
                $this->deferredIndexListener->scheduleForUpdate($this->em->getReference(Asset::class, $assetId));
            }

            $this->em->flush();
        });
    }

    private function upsertAttribute(
        ?Attribute $attribute,
        array $assetsId,
        AttributeDefinition $definition,
        AttributeActionInput $action
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

            $this->attributeAssigner->assignAttributeFromInput($attribute, $action);

            $this->em->persist($attribute);

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

    public function getAttributeDefinitionBySlug(string $workspaceId, string $slug): AttributeDefinition
    {
        $def = $this->em->getRepository(AttributeDefinition::class)->findOneBy([
            'slug' => $slug,
            'workspace' => $workspaceId,
        ]);
        if (!$def instanceof AttributeDefinition) {
            throw new BadRequestHttpException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $slug, $workspaceId));
        }

        return $def;
    }

    private function deleteAttributes(array $assetsId, ?AttributeDefinition $definition, ?RemoteUser $user, array $options = []): void
    {
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
            if ($user instanceof RemoteUser) {
                $qb
                    ->innerJoin('a.definition', 'ad')
                    ->innerJoin('ad.class', 'ac')
                    ->andWhere('ac.public = true OR ace.id IS NOT NULL')
                ;
                $this->joinUserAcl($qb, $user);
            }
        }
        if ($options['id'] ?? null) {
            $qb
                ->andWhere('a.id = :id')
                ->setParameter('id', $options['id']);
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

    private function joinUserAcl(QueryBuilder $queryBuilder, RemoteUser $user): void
    {
        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $user->getId(),
            $user->getGroupIds(),
            'attribute_class',
            'ac',
            PermissionInterface::EDIT,
            false
        );
    }
}
