<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Api\Model\Input\Attribute\BatchAssetAttributeInput;
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

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function handleBatch(Asset $asset, BatchAssetAttributeInput $input): void
    {
        $this->em->wrapInTransaction(function () use ($input, $asset): void {
            foreach ($input->actions as $i => $action) {
                $definition = $action->definitionId ? $this->getAttributeDefinition($asset->getWorkspaceId(), $action->definitionId) : null;

                switch ($action->action) {
                    case self::ACTION_ADD:
                        if (!$definition) {
                            throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                        }
                        if (!$definition->isMultiple()) {
                            throw new BadRequestHttpException(sprintf('Attribute "%s" is not multi-valued in action #%d', $definition->getName(), $i));
                        }

                        $this->upsertAttribute(null, $asset, $definition, $action);
                        break;
                    case self::ACTION_DELETE:
                        $qb = $this->em->createQueryBuilder()
                            ->delete()
                            ->from(Attribute::class, 'a')
                            ->andWhere('a.asset = :asset')
                            ->setParameter('asset', $asset->getId());
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
                    case self::ACTION_SET:
                        if (!$definition) {
                            throw new BadRequestHttpException(sprintf('Missing definitionId in action #%d', $i));
                        }
                        if ($definition->isMultiple()) {
                            throw new BadRequestHttpException(sprintf('Attribute "%s" is a multi-valued in action #%d, use add/delete actions for this kind of attribute', $definition->getName(), $i));
                        }
                        $attribute = $this->em->getRepository(Attribute::class)->findOneBy([
                            'definition' => $definition->getId(),
                            'asset' => $asset->getId(),
                        ]);
                        $this->upsertAttribute($attribute, $asset, $definition, $action);
                        break;
                    case self::ACTION_REPLACE:
                        // TODO
                        break;
                    default:
                        throw new InvalidArgumentException(sprintf('Unsupported action "%s"', $action->action));
                }
            }

            $this->em->flush();
        });
    }

    private function upsertAttribute(?Attribute $attribute, Asset $asset, AttributeDefinition $definition, AttributeActionInput $action): Attribute
    {
        if (null === $attribute) {
            $attribute = new Attribute();
            $attribute->setAsset($asset);
            $attribute->setDefinition($definition);
        }
        $attribute->setValue($action->value);

        $this->em->persist($attribute);

        return $attribute;
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
}
