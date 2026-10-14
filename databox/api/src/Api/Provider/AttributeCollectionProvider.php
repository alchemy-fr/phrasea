<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Attribute;
use App\Security\Voter\AttributeDefinitionVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;

class AttributeCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    public function __construct(
        private readonly AssetPolicyManager $assetPolicyManager,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $asset = $this->getAsset($operation);

        $attributes = $this->em->getRepository(Attribute::class)
            ->getCachedAssetAttributes($asset->getId())
        ;

        // Same visibility as the asset output: the definition policy and the asset policies.
        $filteredDefinitions = $this->assetPolicyManager->getPolicyApplicationFilter($asset)->getFilteredAttributes();
        $canView = [];

        return array_values(array_filter($attributes, function (Attribute $attribute) use ($filteredDefinitions, &$canView): bool {
            $definition = $attribute->getDefinition();
            $definitionId = $definition->getId();

            return !in_array($definitionId, $filteredDefinitions, true)
                && ($canView[$definitionId] ??= $this->security->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition));
        }));
    }
}
