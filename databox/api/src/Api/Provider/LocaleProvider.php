<?php

namespace App\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Model\Locale;
use Symfony\Component\Intl\Locales;

final readonly class LocaleProvider implements ProviderInterface
{
    public function __construct()
    {
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

        $locale = $uriVariables['id'];
        if (!Locales::exists($locale)) {
            return null;
        }

        $parts = \Locale::parseLocale($locale);

        return new Locale(
            id: $locale,
            language: $parts['language'],
            region: $parts['region'] ?? null,
            variant: $parts['variant'] ?? null,
            script: $parts['script'] ?? null,
        );
    }
}
