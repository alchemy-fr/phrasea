<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Integration\ExtraReferenceIntegrationInterface;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationManager;
use App\Integration\IntegrationRegistry;
use App\Integration\UserActionsIntegrationInterface;
use App\Integration\WorkflowIntegrationInterface;
use App\Model\IntegrationType;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class IntegrationTypeProvider implements ProviderInterface
{
    public function __construct(
        private IntegrationManager $integrationManager,
        private IntegrationRegistry $integrationRegistry,
        private TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            return array_map($this->getIntegration(...), $this->integrationRegistry->getIntegrations());
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
        $object->displayName = $integration::getDisplayName();
        $object->name = $integration::getName();
        $object->description = $integration::getDescription()->trans($this->translator);
        $object->categories = array_map(
            fn (IntegrationCategory $category): string => $category->value,
            $integration::getCategories()
        );
        $object->requiresWorkspace = $integration::requiresWorkspace();

        if ($integration instanceof WorkflowIntegrationInterface) {
            $object->features[] = 'workflow';
        }
        if ($integration instanceof UserActionsIntegrationInterface) {
            foreach ($integration->getSupportedContexts() as $context) {
                $object->features[] = $context->value;
            }
        }

        $object->reference = $this->integrationManager->getIntegrationReference($integration);

        if ($integration instanceof ExtraReferenceIntegrationInterface) {
            $object->references = $integration->getExtraReferenceSections();
        }

        return $object;
    }
}
