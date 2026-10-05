<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * A share link only delivers what is still deliverable: accepted assets, out of
 * the trash, through the renditions its policies allow — never the original.
 */
class ShareDeliveryTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    public function testTrashedOrQuarantinedAssetsAreNoLongerDelivered(): void
    {
        $client = static::createClient();
        [$asset, $definitions] = $this->createAssetWithRenditions(['preview' => true]);

        $share = $this->request($client, 'POST', '/shares', self::ADMIN, [
            'assets' => ['/assets/'.$asset->getId()],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $renditionUrl = sprintf('/s/%s/r/%s?token=%s', $share['id'], $definitions['preview'], $share['token']);
        $publicUrl = sprintf('/shares/%s/public?token=%s', $share['id'], $share['token']);

        $client->request('GET', $renditionUrl);
        $this->assertResponseStatusCodeSame(302);

        $em = self::getEntityManager();
        $asset = $em->find(Asset::class, $asset->getId());
        $asset->setStatus(AssetStatusEnum::Quarantined);
        $em->flush();

        $client->request('GET', $renditionUrl);
        $this->assertResponseStatusCodeSame(404);
        $public = $this->request($client, 'GET', $publicUrl);
        $this->assertSame([], $public['assets']);
        $this->assertSame([], $public['alternateUrls']);

        $asset = $em->find(Asset::class, $asset->getId());
        $asset->setStatus(AssetStatusEnum::Accepted);
        $asset->delete();
        $em->flush();

        $client->request('GET', $renditionUrl);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame([], $this->request($client, 'GET', $publicUrl)['assets']);
    }

    public function testPublicShareDoesNotExposeTheSourceFile(): void
    {
        $client = static::createClient();
        [$asset] = $this->createAssetWithRenditions(['preview' => true]);

        $share = $this->request($client, 'POST', '/shares', self::ADMIN, [
            'assets' => ['/assets/'.$asset->getId()],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $public = $this->request($client, 'GET', sprintf('/shares/%s/public?token=%s', $share['id'], $share['token']));
        $source = $public['assets'][0]['source'];
        // Described, not downloadable: the share delivers the renditions its policies allow
        $this->assertSame('image/jpeg', $source['type']);
        $this->assertArrayNotHasKey('url', array_filter($source, fn ($v): bool => null !== $v));
    }

    /**
     * @param array<string, bool> $renditions rendition name => whether its policy is public
     *
     * @return array{Asset, array<string, string>} the asset and the rendition definition IDs by name
     */
    private function createAssetWithRenditions(array $renditions): array
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::ADMIN]);

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $file->setType('image/jpeg');
        $file->setSize(1234);
        $em->persist($file);

        $asset = $this->createAsset(['ownerId' => self::ADMIN, 'no_flush' => true]);
        $asset->setSource($file);

        $policies = [];
        $definitions = [];
        foreach ($renditions as $name => $public) {
            if (!isset($policies[$public])) {
                $policy = new RenditionPolicy();
                $policy->setWorkspace($workspace);
                $policy->setName($public ? 'public' : 'restricted');
                $policy->setPublic($public);
                $policy->setEditable(false);
                $em->persist($policy);
                $policies[$public] = $policy;
            }

            $definition = new RenditionDefinition();
            $definition->setWorkspace($workspace);
            $definition->setPolicy($policies[$public]);
            $definition->setName($name);
            $definition->setTarget(AssetTypeEnum::Both);
            $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
            $em->persist($definition);
            $definitions[$name] = $definition->getId();

            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setDefinition($definition);
            $rendition->setFile($file);
            $em->persist($rendition);
        }
        $em->flush();

        return [$asset, $definitions];
    }

    private function request(Client $client, string $method, string $uri, ?string $userId = null, ?array $json = null): array
    {
        $options = [];
        if (null !== $userId) {
            $options['headers'] = ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)];
        }
        if (null !== $json) {
            $options['json'] = $json;
        }

        $response = $client->request($method, $uri, $options);

        return $response->getStatusCode() < 300 ? $response->toArray() : [];
    }
}
