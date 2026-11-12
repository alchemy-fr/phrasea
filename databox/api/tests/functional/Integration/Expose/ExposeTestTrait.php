<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Expose;

use Alchemy\CoreBundle\Upload\MultipartUploader;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Http\LocaleContext;
use App\Integration\Auth\IntegrationTokenManager;
use App\Integration\Auth\OAuthRedirectUriMatcher;
use App\Integration\Phrasea\Expose\ExposeAssetPublisher;
use App\Integration\Phrasea\Expose\ExposeAttributeHtmlRenderer;
use App\Integration\Phrasea\Expose\ExposeClient;
use App\Integration\Phrasea\Expose\ExposeIntegration;
use App\Integration\Phrasea\PhraseaClientFactory;
use App\Model\AssetTypeEnum;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\FileFetcher;
use App\Service\Clip\MediaClipper;
use App\Service\Storage\RenditionManager;
use Psr\Log\NullLogger;

/**
 * Expose behind a FakeExposeApi, and the Databox side of a publication: an Expose integration,
 * the access of a user, assets with a source file and the renditions used as preview and thumbnail.
 */
trait ExposeTestTrait
{
    protected static function createExposeClient(FakeExposeApi $api): ExposeClient
    {
        $http = $api->createClient();

        return new ExposeClient(
            new PhraseaClientFactory($http, self::getService(IntegrationTokenManager::class)),
            $http,
            self::createFileFetcher(),
            self::getService(AssetNameResolver::class),
            self::getService(AttributesResolver::class),
            self::getService(AttributeTypeRegistry::class),
            self::getService(LocaleContext::class),
            new MultipartUploader($http),
            self::getService(MediaClipper::class),
            new ExposeAttributeHtmlRenderer(),
            self::getService(OAuthRedirectUriMatcher::class),
        );
    }

    protected static function createPublisher(ExposeClient $client): ExposeAssetPublisher
    {
        return new ExposeAssetPublisher($client, self::getService(RenditionManager::class), new NullLogger());
    }

    /**
     * Serves "content of <path>" for each file, without any storage.
     */
    private static function createFileFetcher(): FileFetcher
    {
        return new readonly class extends FileFetcher {
            public function __construct()
            {
            }

            public function isFetchable(File $file): bool
            {
                return true;
            }

            public function getFile(File $file, ?string $path = null): string
            {
                $path ??= (string) tempnam(sys_get_temp_dir(), 'fetch-file');
                file_put_contents($path, 'content of '.$file->getPath());

                return $path;
            }
        };
    }

    protected function createExposeIntegration(?Workspace $workspace, string $ownerId, bool $enabled = true): WorkspaceIntegration
    {
        $em = self::getEntityManager();
        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setIntegration(ExposeIntegration::NAME);
        $integration->setConfig([
            'baseUrl' => FakeExposeApi::BASE_URL,
            'clientId' => 'expose-app',
            'clientUrl' => 'https://expose.phrasea.test',
        ]);
        $integration->setOwnerId($ownerId);
        $integration->setPublic(false);
        $integration->setEnabled($enabled);
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    protected function createExposeToken(WorkspaceIntegration $integration, string $userId): IntegrationToken
    {
        $em = self::getEntityManager();
        $token = new IntegrationToken();
        $token->setIntegration($em->find(WorkspaceIntegration::class, $integration->getId()));
        $token->setUserId($userId);
        $token->setToken([
            'access_token' => 'expose-access-token',
            'expires_at' => time() + 3600,
        ]);
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        $em->persist($token);
        $em->flush();

        return $token;
    }

    protected function createSourceFile(Workspace $workspace, string $originalName, string $type = 'image/jpeg'): File
    {
        $em = self::getEntityManager();
        $file = new File();
        $file->setWorkspace($em->find(Workspace::class, $workspace->getId()));
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('files/'.uniqid());
        $file->setType($type);
        $file->setOriginalName($originalName);
        $file->setSize(42);
        $em->persist($file);
        $em->flush();

        return $file;
    }

    /**
     * An asset with a source file and its preview and thumbnail renditions.
     */
    protected function createPublishableAsset(Workspace $workspace, string $ownerId, string $originalName): Asset
    {
        $em = self::getEntityManager();
        $asset = $this->createAsset([
            'workspace' => $em->find(Workspace::class, $workspace->getId()),
            'ownerId' => $ownerId,
        ]);
        $asset->setSource($this->createSourceFile($workspace, $originalName));
        $em->flush();

        foreach ($this->getPublicDefinitions($workspace) as $definition) {
            $file = $this->createSourceFile($workspace, $definition->getName().'.jpg');
            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setDefinition($definition);
            $rendition->setFile($file);
            $em->persist($rendition);
        }
        $em->flush();

        return $asset;
    }

    /**
     * @return RenditionDefinition[] the definitions used as preview and thumbnail
     */
    private function getPublicDefinitions(Workspace $workspace): array
    {
        $em = self::getEntityManager();
        $definitions = $em->getRepository(RenditionDefinition::class)->findBy([
            'workspace' => $workspace->getId(),
            'name' => ['expose-preview', 'expose-thumbnail'],
        ]);
        if (!empty($definitions)) {
            return $definitions;
        }

        $policy = new RenditionPolicy();
        $policy->setWorkspace($em->find(Workspace::class, $workspace->getId()));
        $policy->setName('Expose test policy');
        $policy->setPublic(true);
        $policy->setEditable(false);
        $em->persist($policy);

        foreach (['preview', 'thumbnail'] as $use) {
            $definition = new RenditionDefinition();
            $definition->setWorkspace($policy->getWorkspace());
            $definition->setPolicy($policy);
            $definition->setName('expose-'.$use);
            $definition->setTarget(AssetTypeEnum::Both);
            $definition->setBuildMode(RenditionDefinition::BUILD_MODE_NONE);
            'preview' === $use ? $definition->setUseAsPreview(true) : $definition->setUseAsThumbnail(true);
            $em->persist($definition);
            $definitions[] = $definition;
        }
        $em->flush();

        return $definitions;
    }
}
