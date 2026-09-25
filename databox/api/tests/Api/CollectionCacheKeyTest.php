<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\AbstractSearchTestCase;

class CollectionCacheKeyTest extends AbstractSearchTestCase
{
    /**
     * A UUID whose leading characters ("2e19") parse as a float greater than
     * PHP_INT_MAX. Cache adapters detect numeric keys with `$key === (string) (int) $key`
     * (see TagAwareAdapter::getItems()) and that cast raises
     * "The float-string ... is not representable as an int" on PHP 8.5.
     *
     * The value is hardcoded so the case is always covered, instead of showing up
     * randomly whenever uuid4() happens to generate such an ID.
     */
    private const string FLOAT_LIKE_UUID = '2e196a9a-7478-4d22-b35a-2422b4e1abbb';

    public function testCollectionWithFloatLikeUuidIsSerializable(): void
    {
        $collection = $this->createCollection([
            'id' => self::FLOAT_LIKE_UUID,
            'name' => 'Float-like UUID collection',
        ]);
        $this->assertSame(self::FLOAT_LIKE_UUID, $collection->getId());
        // Guard: the UUID must really start with an int-overflowing float-string.
        $this->assertMatchesRegularExpression('/^\d+e\d{2,}/', self::FLOAT_LIKE_UUID);

        static::createClient()->request('GET', '/collections/'.self::FLOAT_LIKE_UUID, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'collection',
            'id' => self::FLOAT_LIKE_UUID,
        ]);
    }
}
