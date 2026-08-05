<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\OperationTask\RunContext;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies an attribute-definition-wide operation (materialize the fallback value,
 * or recompute the initial value) to every asset of the definition's workspace.
 *
 * The work runs synchronously within the task worker, streaming over the workspace
 * assets and reporting progress through the RunContext.
 */
final readonly class AttributeDefinitionOperationRunner
{
    final public const string OP_STORE_FALLBACK = 'store_fallback';
    final public const string OP_RECOMPUTE_INITIAL = 'recompute_initial';

    public function __construct(
        private EntityManagerInterface $em,
        private AssetRepository $assetRepository,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeRepository $attributeRepository,
        private FallbackResolver $fallbackResolver,
        private InitialAttributeValuesResolver $initialValueResolver,
        private AttributeAssigner $attributeAssigner,
        private AttributesResolver $attributesResolver,
    ) {
    }

    public function run(string $operation, string $definitionId, RunContext $context): void
    {
        $definition = DoctrineUtil::findStrictByRepo($this->attributeDefinitionRepository, $definitionId);
        $workspace = $definition->getWorkspace();

        $context->start($this->assetRepository->count(['workspace' => $workspace]));

        foreach ($this->assetRepository->iterateIdsByWorkspace($workspace->getId()) as $assetId) {
            $asset = $this->em->find(Asset::class, $assetId);
            if (null !== $asset) {
                $this->applyToAsset($operation, $asset, $definition);
            }

            $context->advance();
        }

        $context->finish();
    }

    private function applyToAsset(string $operation, Asset $asset, AttributeDefinition $definition): void
    {
        match ($operation) {
            self::OP_STORE_FALLBACK => $this->storeFallback($asset, $definition),
            self::OP_RECOMPUTE_INITIAL => $this->recomputeInitial($asset, $definition),
            default => throw new \InvalidArgumentException(sprintf('Unknown operation "%s"', $operation)),
        };
    }

    /**
     * Resolve the definition fallback for the asset and persist it as a real Attribute,
     * only when no value already exists for that definition/locale.
     */
    private function storeFallback(Asset $asset, AttributeDefinition $definition): void
    {
        if (!$definition->isEnabled() || $definition->isMultiple()) {
            return;
        }

        $fallbacks = $definition->getFallback();
        if (empty($fallbacks)) {
            return;
        }

        $index = $this->attributesResolver->buildIndex($this->attributeRepository->getCachedAssetAttributes($asset->getId()));

        $created = 0;
        foreach ($fallbacks as $locale => $template) {
            if (empty($template)) {
                continue;
            }

            $attribute = $this->fallbackResolver->resolveAttrFallback($asset, (string) $locale, $definition, $index);
            if (null !== $attribute) {
                $attribute->setOrigin(Attribute::ORIGIN_FALLBACK_PERSISTED);
                $this->em->persist($attribute);
                ++$created;
            }
        }

        if ($created > 0) {
            $this->em->flush();
            $this->attributeAssigner->resetAssetAttributesCache($asset);
        }
    }

    /**
     * Drop the attributes previously stored for this definition on the asset and recompute
     * them from the definition initialValues / readFromMetadata configuration.
     */
    private function recomputeInitial(Asset $asset, AttributeDefinition $definition): void
    {
        $this->em->wrapInTransaction(function () use ($asset, $definition): void {
            $attributes = $this->attributeRepository->findBy([
                'asset' => $asset->getId(),
                'definition' => $definition->getId(),
            ]);
            foreach ($attributes as $attribute) {
                $this->em->remove($attribute);
            }
            $this->em->flush();

            $this->attributeAssigner->resetAssetAttributesCache($asset);

            $attributes = $this->initialValueResolver->resolveInitialAttributes($asset, $definition);
            foreach ($attributes as $attribute) {
                $this->em->persist($attribute);
            }

            $this->em->flush();
            $this->attributeAssigner->resetAssetAttributesCache($asset);
        });
    }
}
