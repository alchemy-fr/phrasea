<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\AuthBundle\Security\DataboxIntegrationClient;
use Alchemy\CoreBundle\Upload\MultipartUploader;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Integration\IntegrationToken;
use App\Http\LocaleContext;
use App\Integration\Auth\OAuthRedirectUriMatcher;
use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\PhraseaClientFactory;
use App\Model\MediaClip;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\FileFetcher;
use App\Service\Clip\MediaClipper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
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
        private MediaClipper $mediaClipper,
        private ExposeAttributeHtmlRenderer $attributeHtmlRenderer,
        private OAuthRedirectUriMatcher $redirectUriMatcher,
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

    /**
     * Creates a publication owned by the user of the token from a user input: only the main properties
     * are accepted, the parent and the profile by ID or IRI.
     *
     * @return array{id: string, title: string}
     */
    public function createPublicationFromInput(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        array $input,
    ): array {
        $title = $input['title'] ?? null;
        if (!is_string($title) || '' === trim($title)) {
            throw new BadRequestHttpException('The title is required');
        }
        $data = ['title' => trim($title)];
        foreach (['slug', 'description'] as $key) {
            if (null !== $value = self::getOptionalString($input, $key)) {
                $data[$key] = $value;
            }
        }
        if (array_key_exists('enabled', $input)) {
            if (!is_bool($input['enabled'])) {
                throw new BadRequestHttpException('"enabled" must be a boolean');
            }
            // A setting of the publication, inherited from its profile when absent
            $data['config'] = ['enabled' => $input['enabled']];
        }
        if (null !== $parent = self::getOptionalString($input, 'parent')) {
            $data['parent'] = '/publications/'.self::normalizeId($parent);
        }
        if (null !== $profile = self::getOptionalString($input, 'profile')) {
            $data['profile'] = '/publication-profiles/'.self::normalizeId($profile);
        }

        try {
            $publication = $this->createPublication($config, $integrationToken, $data);
        } catch (ClientExceptionInterface $e) {
            throw $this->createExposeError($e, $config);
        }

        return [
            'id' => $publication['id'],
            'title' => $publication['title'] ?? $data['title'],
        ];
    }

    private static function getOptionalString(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('"%s" must be a string', $key));
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * Accepts an ID or an IRI.
     */
    private static function normalizeId(string $value): string
    {
        $pos = strrpos($value, '/');

        return false === $pos ? $value : substr($value, $pos + 1);
    }

    /**
     * The refusal of Expose, as shown to the user: the reason given by Expose (e.g. a slug already used),
     * or what to fix when Expose refuses the access token of the OAuth client of the integration.
     */
    public function createExposeError(ClientExceptionInterface $e, IntegrationConfig $config): HttpException
    {
        $response = $e->getResponse();
        if (Response::HTTP_UNAUTHORIZED === $response->getStatusCode()) {
            return new BadRequestHttpException(sprintf(
                'Expose refused the access token of the OAuth client "%s": the client must be allowed on Expose (created from Databox, redirect URIs under %s only, or listed in EXPOSE_API_ALLOWED_CLIENT_IDS), and the Expose service account needs the "view-clients" role of "realm-management" (run bin/migrate.sh). See doc/tech/Authentication/clients.md.',
                $config['clientId'] ?? '',
                $this->redirectUriMatcher->getClientRootUrl().DataboxIntegrationClient::INTEGRATIONS_PATH,
            ), $e);
        }

        try {
            $detail = $response->toArray(false)['detail'] ?? null;
        } catch (\Throwable) {
            $detail = null;
        }

        return new BadRequestHttpException(is_string($detail) ? $detail : $e->getMessage(), $e);
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
                            $attributeHtml = $this->attributeHtmlRenderer->render(
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
                        $attributeHtml = $this->attributeHtmlRenderer->render(
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
                'title' => $resolvedName,
                'description' => $description,
                'tracking_id' => $asset->getResolvedTrackingId(),
                'translations' => $translations,
            ], $extraData);
        });
    }

    public function postAsset(
        IntegrationConfig $config,
        IntegrationToken $integrationToken,
        string $publicationId,
        Asset $asset,
        array $properties,
        ?MediaClip $clip = null,
    ): string {
        $source = $asset->getSource();
        $fetchedFilePath = $this->fileFetcher->getFile($source);

        try {
            $file = $this->mediaClipper->apply($fetchedFilePath, $source->getType(), $clip);

            $multipart = $this->multipartUploader->upload(
                $this->create($config, $integrationToken),
                $file->path,
                $file->renameFile($source->getOriginalName() ?? 'file'),
                $file->mimeType,
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
            $this->mediaClipper->cleanUp();
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
        ?MediaClip $clip = null,
    ): string {
        $file = $rendition->getFile();
        $subDefFetchedFile = $this->fileFetcher->getFile($file);
        try {
            $clipped = $this->mediaClipper->apply($subDefFetchedFile, $file->getType(), $clip);
            $size = $clipped->transformed ? filesize($clipped->path) : $file->getSize();

            $subDefResponse = $this->create($config, $integrationToken)
                ->request('POST', '/sub-definitions', [
                    'json' => [
                        'asset_id' => $assetId,
                        'name' => $renditionName,
                        'use_as_preview' => 'preview' === $renditionName,
                        'use_as_thumbnail' => 'thumbnail' === $renditionName,
                        'use_as_poster' => 'poster' === $renditionName,
                        'upload' => [
                            'type' => $clipped->mimeType,
                            'size' => $size,
                            'name' => $clipped->renameFile($file->getOriginalName()),
                        ],
                        ...$extraData,
                    ],
                ])
                ->toArray();

            $this->uploadClient->request('PUT', $subDefResponse['uploadURL'], [
                'headers' => [
                    'Content-Type' => $clipped->mimeType,
                    'Content-Length' => filesize($clipped->path),
                ],
                'body' => fopen($clipped->path, 'r'),
            ]);
        } finally {
            @unlink($subDefFetchedFile);
            $this->mediaClipper->cleanUp();
        }

        return $subDefResponse['id'];
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
