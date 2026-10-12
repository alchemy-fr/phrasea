<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use App\Service\Workspace\WorkspaceCreator;
use App\Service\Workspace\WorkspaceDefaults;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Helpers shared by the rendition / policy / export API tests.
 */
trait RenditionTestTrait
{
    /**
     * Creates a workspace (with its default "Public" rendition policy and the
     * Main > Preview > Thumbnail definitions chain), with a distinct slug so that
     * several workspaces can coexist in a test.
     *
     * @return array{0: Workspace, 1: WorkspaceDefaults}
     */
    private function createWorkspaceWithDefaults(string $slug, string $ownerId = 'custom_owner', bool $public = false): array
    {
        $em = self::getEntityManager();

        $workspace = new Workspace();
        $workspace->setName('Workspace '.$slug);
        $workspace->setSlug($slug);
        $workspace->setOwnerId($ownerId);
        $workspace->setEnabledLocales(['en', 'fr']);
        $workspace->setPublic($public);

        /** @var WorkspaceCreator $creator */
        $creator = self::getService(WorkspaceCreator::class);
        $defaults = $creator->createWorkspace($workspace);
        $em->flush();

        $this->addUserOnWorkspace($ownerId, $workspace->getId());

        return [$workspace, $defaults];
    }

    private function createRenditionPolicy(Workspace $workspace, string $name, bool $public = true, bool $editable = true): RenditionPolicy
    {
        $em = self::getEntityManager();

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName($name);
        $policy->setPublic($public);
        $policy->setEditable($editable);
        $em->persist($policy);
        $em->flush();

        return $policy;
    }

    private function createRenditionDefinition(Workspace $workspace, RenditionPolicy $policy, string $name, array $options = []): RenditionDefinition
    {
        $em = self::getEntityManager();

        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName($name);
        $definition->setTarget($options['target'] ?? AssetTypeEnum::Both);
        $definition->setBuildMode($options['buildMode'] ?? RenditionDefinition::BUILD_MODE_PICK_SOURCE);
        $definition->setParent($options['parent'] ?? null);
        $definition->setSubstitutable($options['substitutable'] ?? true);
        $definition->setPriority($options['priority'] ?? 0);
        $definition->setUseAsMain($options['useAsMain'] ?? false);
        if (isset($options['definition'])) {
            $definition->setDefinition($options['definition']);
        }
        if (isset($options['key'])) {
            $definition->setKey($options['key']);
        }
        $em->persist($definition);
        $em->flush();

        return $definition;
    }

    private function createUrlFile(Workspace $workspace, string $url = 'https://cdn.example.com/file.jpg'): File
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_URL);
        $file->setPath($url);
        $file->setPathPublic(true);
        $file->setType('image/jpeg');
        $file->setExtension('jpg');
        $file->setOriginalName(basename($url));
        $em->persist($file);
        $em->flush();

        return $file;
    }

    private function createAssetRendition(Asset $asset, RenditionDefinition $definition, ?File $file, array $options = []): AssetRendition
    {
        $em = self::getEntityManager();

        $rendition = new AssetRendition();
        $rendition->setAsset($asset);
        $rendition->setDefinition($definition);
        $rendition->setFile($file);
        $rendition->setLocked($options['locked'] ?? false);
        $rendition->setSubstituted($options['substituted'] ?? false);
        $rendition->setBuildHash($options['buildHash'] ?? null);
        $em->persist($rendition);
        $em->flush();

        return $rendition;
    }

    private function jsonRequest(string $method, string $uri, ?string $userId, ?array $json = null, array $options = []): ResponseInterface
    {
        // The first request shares the entity manager used to set the test up:
        // start from a clean identity map so that the API reloads everything.
        self::getEntityManager()->clear();

        if (null !== $userId) {
            $options['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }
        if (null !== $json) {
            $options['json'] = $json;
            if ('PATCH' === $method) {
                $options['headers']['Content-Type'] = 'application/merge-patch+json';
            }
        }

        return static::createClient()->request($method, $uri, $options);
    }

    /**
     * @return string[]
     */
    private function memberIds(ResponseInterface $response): array
    {
        return array_column($response->toArray()['member'], 'id');
    }
}
