<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Upload\MultipartUploader;
use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Integration\IntegrationToken;
use App\Http\LocaleContext;
use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\PhraseaClientFactory;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\FileFetcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
        private HttpClientInterface $uploadClient,
        private FileFetcher $fileFetcher,
        private AssetNameResolver $assetNameResolver,
        private AttributesResolver $attributesResolver,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private LocaleContext $localeContext,
        private MultipartUploader $multipartUploader,
    ) {
    }

    private function create(IntegrationConfig $config, IntegrationToken $integrationToken): HttpClientInterface
    {
        return $this->clientFactory->create(
            $config['baseUrl'],
            $config['clientId'],
            $integrationToken,
        );
    }

    public function getAuthenticatedClient(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
    ): HttpClientInterface {
        return $this->create($config, $integrationToken);
    }

    public function createPublication(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        array $data,
    ): array {
        return $this->create($config, $integrationToken)
            ->request('POST', '/publications', [
                'json' => $data,
            ])
            ->toArray();
    }

    public function deletePublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/publications/'.$id);
    }

    public function getPublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): array
    {
        return $this->create($config, $integrationToken)
            ->request('GET', '/publications/'.$id)
            ->toArray();
    }

    public function getAssetProperties(
        Asset $asset,
        array $extraData = [],
    ): array {
        return $this->localeContext->wrapLocaleLess(function () use ($asset, $extraData): array {
            $wsLocales = $asset->getWorkspace()->getEnabledLocales();

            $attributesIndex = $this->attributesResolver->resolveAssetAttributes($asset, true);
            $resolvedName = $this->assetNameResolver->resolveNameAsString($asset, $attributesIndex);

            $descriptionTranslations = [];
            foreach ($attributesIndex->getDefinitions() as $definitionIndex) {
                $attrTranslations = [];

                foreach ($definitionIndex->getLocales() as $locale => $attribute) {
                    $definition = $definitionIndex->getDefinition();
                    $type = $definition->getType();
                    $attributeType = $this->attributeTypeRegistry->getStrictType($type);

                    if ($attributeType instanceof EntityAttributeType) {
                        $entityTranslations = [];
                        if ($definition->isMultiple()) {
                            foreach ($wsLocales as $wsLocale) {
                                $entityTranslations[$wsLocale] ??= [];
                                foreach ($attribute as $a) {
                                    $v = $attributeType->getEntityBestTranslation($a->getValue(), $wsLocale);
                                    if (null !== $v) {
                                        $entityTranslations[$wsLocale][] = $v;
                                    }
                                }
                            }
                        } else {
                            foreach ($wsLocales as $wsLocale) {
                                $v = $attributeType->getEntityBestTranslation($attribute->getValue(), $wsLocale);
                                if (null !== $v) {
                                    $entityTranslations[$wsLocale] = $v;
                                }
                            }
                        }

                        foreach ($entityTranslations as $eLocale => $entityTranslation) {
                            $attributeHtml = $this->getAttributeHtml(
                                $definition,
                                $definition->isMultiple() ? array_map(fn (?string $v,
                                ): ?string => $v, $entityTranslation) : $entityTranslation,
                                $eLocale
                            );
                            if (!empty($attributeHtml)) {
                                $attrTranslations[$eLocale] = $attributeHtml;
                            }
                        }
                    } else {
                        $attributeHtml = $this->getAttributeHtml(
                            $definition,
                            $definition->isMultiple() ? array_map(fn (Attribute $a,
                            ): string => $attributeType->getStringValue($a->getValue(), $locale), $attribute) : $attributeType->getStringValue($attribute->getValue(), $locale),
                            $locale
                        );
                        if (!empty($attributeHtml)) {
                            $attrTranslations[$locale] = $attributeHtml;
                        }
                    }
                }

                // add fallback if not set
                $attrTranslations[AttributeInterface::NO_LOCALE] ??= reset($attrTranslations);

                // add fallback for all workspace locales
                foreach ($wsLocales as $wsLocale) {
                    $attrTranslations[$wsLocale] ??= $attrTranslations[AttributeInterface::NO_LOCALE];
                }

                foreach ($attrTranslations as $locale => $translation) {
                    $descriptionTranslations[$locale] ??= [];
                    $descriptionTranslations[$locale][] = $translation;
                }
            }

            $translations = [];
            $description = null;
            if (!empty($descriptionTranslations)) {
                $descriptionTranslations = array_map(fn (array $ltr): string => sprintf('<div class="attributes">
    %s</div>', implode("\n", $ltr)), $descriptionTranslations);

                if (isset($descriptionTranslations[AttributeInterface::NO_LOCALE])) {
                    $description = $descriptionTranslations[AttributeInterface::NO_LOCALE];
                    unset($descriptionTranslations[AttributeInterface::NO_LOCALE]);
                } else {
                    $description = array_shift($descriptionTranslations);
                }

                if (!empty($descriptionTranslations)) {
                    $translations['description'] = $descriptionTranslations;
                }
            }

            return array_merge([
                'name' => $resolvedName,
                'description' => $description,
                'tracking_id' => $asset->getResolvedTrackingId(),
                'translations' => $translations,
            ], $extraData);
        });
    }

    private function getAttributeHtml(
        AttributeDefinition $definition,
        string|array|null $value,
        ?string $locale = null,
    ): ?string {
        if (empty($value)) {
            return null;
        }

        $hasLocale = $locale && AttributeInterface::NO_LOCALE !== $locale;

        $attributeName = $definition->getName();
        if ($hasLocale) {
            $nameTranslations = $definition->getTranslations()['name'] ?? [];
            if (!empty($nameTranslations)) {

                $bestLocale = LocaleUtil::getBestLocale(array_keys($nameTranslations), [$locale]);
                if ($bestLocale) {
                    $attributeName = $nameTranslations[$bestLocale];
                }
            }
        }

        return sprintf(
            '  <div class="attribute-group">
    <div class="attribute-name attribute-name-type-%1$s attribute-name-%2$s">%3$s</div>
    <div class="attribute-value attribute-value-type-%1$s attribute-name-%2$s"%5$s>%4$s</div>
  </div>
',
            $definition->getType(),
            $definition->getSlug(),
            $attributeName,
            $definition->isMultiple() ? implode(', ', $value) : $value,
            $hasLocale ? ' lang="'.$locale.'"' : '',
        );
    }

    public function postAsset(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        string $publicationId,
        Asset $asset,
        array $properties,
    ): string {
        $source = $asset->getSource();
        $fetchedFilePath = $this->fileFetcher->getFile($source);

        try {
            $multipart = $this->multipartUploader->upload(
                $this->create($config, $integrationToken),
                $fetchedFilePath,
                $source->getOriginalName() ?? 'file',
                $source->getType(),
            );

            $data = array_merge([
                'publication_id' => $publicationId,
                'asset_id' => $asset->getId(),
                'multipart' => $multipart,
            ], $properties);

            $pubAsset = $this->create($config, $integrationToken)
                ->request('POST', '/assets', [
                    'json' => $data,
                ])
                ->toArray();
        } finally {
            @unlink($fetchedFilePath);
        }

        return $pubAsset['id'];
    }

    public function putAsset(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        string $assetId,
        array $data,
    ): array {
        return $this->create($config, $integrationToken)
            ->request('PATCH', '/assets/'.$assetId, [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => $data,
            ])
            ->toArray();
    }

    public function postSubDefinition(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        string $assetId,
        string $renditionName,
        AssetRendition $rendition,
        array $extraData = [],
    ): void {
        $file = $rendition->getFile();
        $subDefFetchedFile = $this->fileFetcher->getFile($file);
        try {
            $subDefResponse = $this->create($config, $integrationToken)
                ->request('POST', '/sub-definitions', [
                    'json' => [
                        'asset_id' => $assetId,
                        'name' => $renditionName,
                        'use_as_preview' => 'preview' === $renditionName,
                        'use_as_thumbnail' => 'thumbnail' === $renditionName,
                        'use_as_poster' => 'poster' === $renditionName,
                        'upload' => [
                            'type' => $file->getType(),
                            'size' => $file->getSize(),
                            'name' => $file->getOriginalName(),
                        ],
                        ...$extraData,
                    ],
                ])
                ->toArray();

            $this->uploadClient->request('PUT', $subDefResponse['uploadURL'], [
                'headers' => [
                    'Content-Type' => $file->getType(),
                    'Content-Length' => filesize($subDefFetchedFile),
                ],
                'body' => fopen($subDefFetchedFile, 'r'),
            ]);
        } finally {
            @unlink($subDefFetchedFile);
        }
    }

    public function deleteAsset(IntegrationConfig $config, IntegrationToken $integrationToken, string $assetId): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/assets/'.$assetId);
    }

    public function deleteSubDefinition(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        string $subDefinitionId,
    ): void {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/sub-definitions/'.$subDefinitionId);
    }
}
