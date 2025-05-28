<?php

namespace App\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationManager;
use App\Integration\IntegrationRegistry;
use App\Model\IntegrationType;

final readonly class IntegrationTypeProvider implements ProviderInterface
{
    public function __construct(
        private IntegrationManager $integrationManager,
        private IntegrationRegistry $integrationRegistry,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            return array_map(function (IntegrationInterface $integration): IntegrationType {
                return $this->getIntegration($integration);
            }, $this->integrationRegistry->getIntegrations());
        }

        $integration = $this->integrationRegistry->getIntegration(IntegrationType::denormalizeId($uriVariables['id']));
        if (null === $integration) {
            return null;
        }

        return $this->getIntegration($integration);
    }

    private function getIntegration(IntegrationInterface $integration): IntegrationType
    {
        $object = new IntegrationType();
        $object->id = IntegrationType::normalizeId($integration::getName());
        $object->title = $integration::getTitle();
        $object->name = $integration::getName();

        $object->reference = $this->integrationManager->getIntegrationReference($integration);

        return $object;
    }
}
