<?php

namespace App\Integration\Phrasea\Expose;

use App\Asset\Attribute\AssetTitleResolver;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\FileFetcher;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Integration\IntegrationToken;
use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\PhraseaClientFactory;
use App\Storage\RenditionManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
        private HttpClientInterface $uploadClient,
        private FileFetcher $fileFetcher,
        private AssetTitleResolver $assetTitleResolver,
        private AttributesResolver $attributesResolver,
        private RenditionManager $renditionManager,
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

    public function getAuthenticatedClient(IntegrationConfig $config, IntegrationToken $integrationToken): HttpClientInterface
    {
        return $this->create($config, $integrationToken);
    }

    public function createPublications(IntegrationConfig $config, IntegrationToken $integrationToken, array $data): array
    {
        return $this->create($config, $integrationToken)
            ->request('POST', '/publications', [
                'json' => $data,
            ])
            ->toArray();
    }

    public function deletePublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/publications/'.$id)
        ;
    }

    public function getPublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): array
    {
        return $this->create($config, $integrationToken)
            ->request('GET', '/publications/'.$id)
            ->toArray()
        ;
    }

    public function postAsset(IntegrationConfig $config, IntegrationToken $integrationToken, string $publicationId, Asset $asset, array $extraData = []): void
    {
        $attributesIndex = $this->attributesResolver->resolveAssetAttributes($asset, true);
        $resolvedTitleAttr = $this->assetTitleResolver->resolveTitle($asset, $attributesIndex, []);
        if ($resolvedTitleAttr instanceof Attribute) {
            $resolvedTitle = $resolvedTitleAttr->getValue();
        } else {
            $resolvedTitle = $resolvedTitleAttr;
        }

        $descriptionTranslations = [];
        foreach ($attributesIndex->getDefinitions() as $definitionIndex) {
            $attrTranslations = [];

            foreach ($definitionIndex->getLocales() as $locale => $attribute) {
                $definition = $definitionIndex->getDefinition();
                $fieldType = $definition->getFieldType();

                $attrTranslations[$locale] = sprintf(
                    '  <dt class="field-title field-type-%1$s field-name-%2$s">%3$s</dt>
  <dd class="value field-type-%1$s field-name-%2$s">%4$s</dd>
',
                    $fieldType,
                    $definition->getSlug(),
                    $definition->getName(),
                    $definition->isMultiple() ? implode(', ', array_map(fn (Attribute $a): ?string => $a->getValue(), $attribute)) : $attribute->getValue(),
                );
            }

            // adding fallback if not set
            if (!isset($attrTranslations[IndexMappingUpdater::NO_LOCALE])) {
                $attrTranslations[IndexMappingUpdater::NO_LOCALE] = reset($attrTranslations);
            }

            foreach ($attrTranslations as $locale => $translation) {
                $descriptionTranslations[$locale] ??= [];
                $descriptionTranslations[$locale][] = $translation;
            }
        }

        $translations = [];
        $description = null;
        if (!empty($descriptionTranslations)) {
            $descriptionTranslations = array_map(function (array $ltr): string {
                return sprintf('<dl>
%s</dl>', implode("\n", $ltr));
            }, $descriptionTranslations);

            if (isset($descriptionTranslations[IndexMappingUpdater::NO_LOCALE])) {
                $description = $descriptionTranslations[IndexMappingUpdater::NO_LOCALE];
                unset($descriptionTranslations[IndexMappingUpdater::NO_LOCALE]);
            } else {
                $description = array_shift($descriptionTranslations);
            }

            if (!empty($descriptionTranslations)) {
                $translations['description'] = $descriptionTranslations;
            }
        }

        $source = $asset->getSource();

        $fetchedFilePath = $this->fileFetcher->getFile($source);
        try {
            $data = array_merge([
                'publication_id' => $publicationId,
                'asset_id' => $asset->getId(),
                'title' => $resolvedTitle,
                'description' => $description,
                'translations' => $translations,
                'upload' => [
                    'type' => $source->getType(),
                    'size' => $source->getSize(),
                    'name' => $source->getOriginalName(),
                ],
            ], $extraData);

            $pubAsset = $this->create($config, $integrationToken)
                ->request('POST', '/assets', [
                    'json' => $data,
                ])
                ->toArray()
            ;
            $exposeAssetId = $pubAsset['id'];

            $this->uploadClient->request('PUT', $pubAsset['uploadURL'], [
                'headers' => [
                    'Content-Type' => $source->getType(),
                    'Content-Length' => filesize($fetchedFilePath),
                ],
                'body' => fopen($fetchedFilePath, 'r'),
            ]);

            foreach ([
                'preview',
                'thumbnail',
            ] as $renditionName) {
                if (null !== $rendition = $this->renditionManager->getAssetRenditionUsedAs($renditionName, $asset->getId())) {
                    $file = $rendition->getFile();
                    $subDefFetchedFile = $this->fileFetcher->getFile($file);
                    try {
                        $subDefResponse = $this->create($config, $integrationToken)
                            ->request('POST', '/sub-definitions', [
                                'json' => [
                                    'asset_id' => $exposeAssetId,
                                    'name' => $renditionName,
                                    'use_as_preview' => 'preview' === $renditionName,
                                    'use_as_thumbnail' => 'thumbnail' === $renditionName,
                                    'use_as_poster' => 'poster' === $renditionName,
                                    'upload' => [
                                        'type' => $file->getType(),
                                        'size' => $file->getSize(),
                                        'name' => $file->getOriginalName(),

                                    ],
                                ],
                            ])
                            ->toArray()
                        ;

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
            }
        } finally {
            @unlink($fetchedFilePath);
        }
    }

    public function deleteAsset(IntegrationConfig $config, IntegrationToken $integrationToken, string $assetId): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/assets/'.$assetId)
        ;
    }
}
