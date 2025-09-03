<?php

namespace App\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationManager;
use App\Integration\IntegrationRegistry;
use App\Model\IntegrationType;
use App\Model\Locale;
use Symfony\Component\Intl\Locales;

final readonly class LocaleProvider implements ProviderInterface
{
    public function __construct(
        private IntegrationManager $integrationManager,
        private IntegrationRegistry $integrationRegistry,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            return array_map(function (string $locale): Locale {
                $parts = \Locale::parseLocale($locale);

                return new Locale(
                    id: $locale,
                    language: $parts['language'],
                    region: $parts['region'] ?? null,
                    variant: $parts['variant'] ?? null,
                    script: $parts['script'] ?? null,
                );
            }, array_keys(Locales::getNames()));
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
