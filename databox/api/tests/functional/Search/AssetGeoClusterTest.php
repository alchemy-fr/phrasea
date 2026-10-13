<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\GeoPointAttributeType;
use App\Tests\Functional\AbstractSearchTestCase;

class AssetGeoClusterTest extends AbstractSearchTestCase
{
    private const string FIELD = 'location_geo-point_s';

    private ?string $newYorkAssetId = null;

    private function createGeolocatedAssets(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );

        $definition = $this->createAttributeDefinition([
            'name' => 'Location',
            'slug' => 'location',
            'type' => GeoPointAttributeType::NAME,
        ]);

        foreach (['48.8566,2.3522', '48.8606,2.3376', '48.853,2.3499'] as $i => $position) {
            $this->createAsset([
                'name' => 'Paris '.$i,
                'ownerId' => KeycloakClientTestMock::USER_UID,
                'attributes' => [['definition' => $definition, 'value' => $position]],
            ]);
        }
        $this->newYorkAssetId = $this->createAsset([
            'name' => 'New York',
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'attributes' => [['definition' => $definition, 'value' => '40.7128,-74.006']],
        ])->getId();
        $this->createAsset([
            'name' => 'Nowhere',
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    private function requestClusters(array $query, int $expectedCode = 200): array
    {
        $response = static::createClient()->request('GET', '/assets/geo-clusters', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'query' => $query,
        ]);

        $this->assertSame($expectedCode, $response->getStatusCode());

        return $response->toArray(false);
    }

    public function testClustersGeolocatedAssets(): void
    {
        $this->createGeolocatedAssets();

        $data = $this->requestClusters(['field' => self::FIELD, 'precision' => 3]);

        $this->assertSame(5, $data['total']);
        $this->assertSame(4, $data['geolocated']);
        $this->assertEqualsWithDelta(48.8606, $data['bounds']['top'], 0.001);
        $this->assertEqualsWithDelta(-74.006, $data['bounds']['left'], 0.001);

        $clusters = $data['clusters'];
        usort($clusters, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $this->assertCount(2, $clusters);

        $this->assertSame(3, $clusters[0]['count']);
        $this->assertEqualsWithDelta(48.857, $clusters[0]['lat'], 0.01);
        $this->assertEqualsWithDelta(2.3466, $clusters[0]['lng'], 0.01);
        $this->assertNull($clusters[0]['assetId']);
        $this->assertEqualsWithDelta(48.853, $clusters[0]['bounds']['bottom'], 0.001);

        $this->assertSame(1, $clusters[1]['count']);
        $this->assertSame($this->newYorkAssetId, $clusters[1]['assetId']);
    }

    public function testClustersWithinBoundsAndConditions(): void
    {
        $this->createGeolocatedAssets();

        $data = $this->requestClusters([
            'field' => self::FIELD,
            'precision' => 3,
            'bounds' => '60,-10,40,20',
        ]);
        $this->assertSame(4, $data['geolocated']);
        $this->assertCount(1, $data['clusters']);
        $this->assertSame(3, $data['clusters'][0]['count']);

        $data = $this->requestClusters([
            'field' => self::FIELD,
            'precision' => 3,
            'conditions' => ['location WITHIN CIRCLE (48.8566, 2.3522, "100m")'],
        ]);
        $this->assertSame(1, $data['total']);
        $this->assertSame(1, $data['geolocated']);

        $data = $this->requestClusters([
            'field' => self::FIELD,
            'precision' => 3,
            'conditions' => ['location WITHIN CIRCLE (40.71, -74.0, "2km") OR location WITHIN CIRCLE (48.8566, 2.3522, "100m")'],
        ]);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['clusters']);
    }

    public function testDoesNotClusterAnAttributeTheUserCannotRead(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );
        $definition = $this->createAttributeDefinition([
            'name' => 'Location',
            'slug' => 'location',
            'type' => GeoPointAttributeType::NAME,
            'policy' => $this->createAttributePolicy([
                'name' => 'Restricted',
                'public' => false,
            ]),
        ]);
        $this->createAsset([
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'attributes' => [['definition' => $definition, 'value' => '48.8566,2.3522']],
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $data = $this->requestClusters(['field' => self::FIELD, 'precision' => 3]);

        $this->assertSame(1, $data['total']);
        $this->assertSame(0, $data['geolocated']);
        $this->assertSame([], $data['clusters']);
        $this->assertNull($data['bounds']);
    }

    public function testRejectsInvalidParameters(): void
    {
        $this->createGeolocatedAssets();

        $this->requestClusters(['field' => 'title_text_s'], 400);
        $this->requestClusters(['field' => self::FIELD, 'bounds' => '1,2,3'], 400);
        $this->requestClusters(['field' => self::FIELD, 'bounds' => '10,0,20,5'], 400);
    }
}
