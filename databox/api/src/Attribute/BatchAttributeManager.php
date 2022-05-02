<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BatchAttributeManager
{
    public const ACTION_SET = 'set';
    public const ACTION_DELETE = 'delete';
    public const ACTION_REPLACE = 'replace';
    public const ACTION_ADD = 'add';

    private EntityManagerInterface $em;
    private AttributeAssigner $attributeAssigner;

    public function __construct(EntityManagerInterface $em, AttributeAssigner $attributeAssigner)
    {
        $this->em = $em;
        $this->attributeAssigner = $attributeAssigner;
    }

    public function handleMultiAssetBatch(AttributeBatchUpdateInput $input): void
    {
        if (!is_array($input->assets)) {
            throw new InvalidArgumentException(sprintf('Missing "assets" property'));
        }
        if (empty($input->assets)) {
            return;
        }
        $firstId = $input->assets[0];
        /** @var Asset $assetOne */
        $assetOne = $this->em->getRepository(Asset::class)->find($firstId);
        if (!$assetOne instanceof Asset) {
            throw new InvalidArgumentException(sprintf('Asset "%s" not found', $firstId));
        }

        $assetIds = array_map(function (array $row): string {
            return $row['id'];
        }, $this->em->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a')
            ->andWhere('a.workspace = :w')
            ->setParameter('w', $assetOne->getWorkspaceId())
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $input->assets)
            ->getQuery()
            ->getScalarResult()
        );

        $this->handleBatch($assetOne->getWorkspaceId(), $assetIds, $input);
    }

    public function handleBatch(string $workspaceId, array $assetsId, AssetAttributeBatchUpdateInput $input): void
    {
        $this->em->wrapInTransaction(function () use ($input, $assetsId, $workspaceId): void {
            foreach ($input->actions as $i => $action) {
                if ($action->definitionId) {
                    $definition = $this->getAttributeDefinition($workspaceId, $action->definitionId);
                } else if ($action->name) {
                    $definition = $this->getAttributeDefinitionByName($workspaceId, $action->name);
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
                        $this->deleteAttributes($assetsId, $definition, [
                            'id' => $action->id,
                        ]);
                        break;
                    case self::ACTION_SET:
                        if (!$definition) {
                            throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                        }
                        if ($definition->isMultiple()) {
                            if (!is_array($action->value)) {
                                throw new BadRequestHttpException(sprintf(
                                    'Attribute "%s" is a multi-valued in action #%d, use add/delete actions for this kind of attribute or pass an array in "value"', $definition->getName(), $i));
                            }

                            $this->deleteAttributes($assetsId, $definition);
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
                        }
                        if ($action->id) {
                            $qb
                                ->andWhere('a.id = :id')
                                ->setParameter('id', $action->id);
                        }
                        $qb->getQuery()->execute();
                        break;
                    default:
                        throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action->action));
                }
            }

            $this->em->flush();
        });
    }

    private function upsertAttribute(?Attribute $attribute, array $assetsId, AttributeDefinition $definition, AttributeActionInput $action): void
    {
        if (null !== $attribute && count($assetsId) > 1) {
            throw new InvalidArgumentException(sprintf('Attribute update is provided with many assets ID'));
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

    private function getAttributeDefinitionByName(string $workspaceId, string $name): AttributeDefinition
    {
        $def = $this->em->getRepository(AttributeDefinition::class)->findOneBy([
            'name' => $name,
            'workspace' => $workspaceId,
        ]);
        if (!$def instanceof AttributeDefinition) {
            throw new BadRequestHttpException(sprintf('Attribute definition "%s" not found in workspace "%s"', $name, $workspaceId));
        }

        return $def;
    }

    function deleteAttributes(array $assetsId, ?AttributeDefinition $definition, array $options = []): void
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
        }
        if ($options['id'] ?? null) {
            $qb
                ->andWhere('a.id = :id')
                ->setParameter('id', $options['id']);
        }
        $qb->getQuery()->execute();
    }
}
