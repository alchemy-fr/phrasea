<?php

namespace App\Integration\Phrasea\Expose;

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
use App\Service\Asset\Attribute\AssetTitleResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\FileFetcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
        private HttpClientInterface $uploadClient,
        private FileFetcher $fileFetcher,
        private AssetTitleResolver $assetTitleResolver,
        private AttributesResolver $attributesResolver,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private LocaleContext $localeContext,
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

    public function createPublications(
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
            $resolvedTitleAttr = $this->assetTitleResolver->resolveTitle($asset, $attributesIndex, []);
            if ($resolvedTitleAttr instanceof Attribute) {
                $type = $this->attributeTypeRegistry->getStrictType($resolvedTitleAttr->getDefinition()->getFieldType());
                $resolvedTitle = $type->getStringValue($resolvedTitleAttr->getValue());
            } else {
                $resolvedTitle = $resolvedTitleAttr;
            }

            $descriptionTranslations = [];
            foreach ($attributesIndex->getDefinitions() as $definitionIndex) {
                $attrTranslations = [];

                foreach ($definitionIndex->getLocales() as $locale => $attribute) {
                    $definition = $definitionIndex->getDefinition();
                    $fieldType = $definition->getFieldType();
                    $type = $this->attributeTypeRegistry->getStrictType($fieldType);

                    $attrTranslations[$locale] = $this->getAttributeHtml(
                        $definition,
                        $definition->isMultiple() ? array_map(fn (Attribute $a,
                        ): ?string => $type->getStringValue($a->getValue()), $attribute) : $type->getStringValue($attribute->getValue()),
                        $locale
                    );

                    if ($type instanceof EntityAttributeType) {
                        $entityTranslations = [];
                        if ($definition->isMultiple()) {
                            foreach ($wsLocales as $wsLocale) {
                                $entityTranslations[$wsLocale] ??= [];
                                foreach ($attribute as $a) {
                                    $entityTranslations[$wsLocale][] = $type->getEntityBestTranslation($a->getValue(), $wsLocale);
                                }
                            }
                        } else {
                            foreach ($wsLocales as $wsLocale) {
                                $entityTranslations[$wsLocale] = $type->getEntityBestTranslation($attribute->getValue(), $wsLocale);
                            }
                        }

                        foreach ($entityTranslations as $eLocale => $entityTranslation) {
                            $attrTranslations[$eLocale] = $this->getAttributeHtml(
                                $definition,
                                $definition->isMultiple() ? array_map(fn (?string $v): ?string => $v, $entityTranslation) : $entityTranslation,
                                $eLocale
                            );
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
                $descriptionTranslations = array_map(function (array $ltr): string {
                    return sprintf('<dl>
    %s</dl>', implode("\n", $ltr));
                }, $descriptionTranslations);

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
                'title' => $resolvedTitle,
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
    ): string {
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
            '  <dt class="field-title field-type-%1$s field-name-%2$s">%3$s</dt>
  <dd class="value field-type-%1$s field-name-%2$s"%5$s>%4$s</dd>
',
            $definition->getFieldType(),
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
        $fileSize = filesize($fetchedFilePath);

        // @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/qfacts.html
        $partSize = 100 * 1024 * 1024; // 100Mb

        try {
            $uploadsData = [
                'filename' => $source->getOriginalName() ?? 'file',
                'type' => $source->getType(),
                'size' => (int) $source->getSize(),
            ];

            $resUploads = $this->create($config, $integrationToken)
                ->request('POST', '/uploads', [
                    'json' => $uploadsData,
                ])
                ->toArray();

            $mUploadId = $resUploads['id'];

            $parts['Parts'] = [];

            try {
                $fd = fopen($fetchedFilePath, 'r');
                $alreadyUploaded = 0;

                $partNumber = 1;

                $retryCount = 3;

                while (($fileSize - $alreadyUploaded) > 0) {
                    $resUploadPart = $this->create($config, $integrationToken)
                        ->request('POST', '/uploads/'.$mUploadId.'/part', [
                            'json' => ['part' => $partNumber],
                        ])
                        ->toArray();

                    if (($fileSize - $alreadyUploaded) < $partSize) {
                        $partSize = $fileSize - $alreadyUploaded;
                    }

                    $headerPutPart = $this->putPart($resUploadPart['url'], $fd, $partSize, $retryCount);

                    $alreadyUploaded += $partSize;

                    $parts['Parts'][$partNumber] = [
                        'PartNumber' => $partNumber,
                        'ETag' => current($headerPutPart['etag']),
                    ];

                    ++$partNumber;
                }

                fclose($fd);
            } catch (\Throwable  $e) {
                $this->create($config, $integrationToken)
                    ->request('DELETE', '/uploads/'.$mUploadId);

                throw $e;
            }

            $data = array_merge([
                'publication_id' => $publicationId,
                'asset_id' => $asset->getId(),
                'multipart' => [
                    'uploadId' => $mUploadId,
                    'parts' => $parts['Parts'],
                ],
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

    public function patchAsset(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        string $assetId,
        array $data,
    ): array {
        return $this->create($config, $integrationToken)
            ->request('PUT', '/assets/'.$assetId, [
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

    private function putPart(string $url, mixed &$handleFile, int $partSize, int $retryCount): array
    {
        if ($retryCount > 0) {
            --$retryCount;
            try {
                $maxToRead = $partSize;
                $alreadyRead = 0;

                return $this->uploadClient->request('PUT', $url, [
                    'headers' => [
                        'Content-Length' => $partSize,
                    ],
                    'body' => function ($size) use (&$handleFile, $maxToRead, &$alreadyRead): mixed {
                        $toRead = min($size, $maxToRead - $alreadyRead);
                        $alreadyRead += $toRead;

                        return fread($handleFile, $toRead);
                    },
                ])->getHeaders();
            } catch (\Throwable $e) {
                if (0 == $retryCount) {
                    throw $e;
                }

                return $this->putPart($url, $handleFile, $partSize, $retryCount);
            }
        } else {
            return [];
        }
    }
}
