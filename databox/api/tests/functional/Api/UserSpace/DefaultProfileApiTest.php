<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Profile\Profile;
use App\Security\Voter\DataboxExtraPermissionInterface;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Default display profiles (the "defaultProfile" flag) and their resolution by
 * GET /profiles/user-default.
 */
final class DefaultProfileApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    private const string GROUP = KeycloakClientTestMock::GROUP_UID;
    private const string OTHER_GROUP = '88888888-fc1a-492b-9e76-c2e8e6979786';

    /**
     * @param string[] $groups the groups of the token
     */
    private function apiAs(string $method, string $uri, string $userId, array $groups = []): ResponseInterface
    {
        return static::createClient()->request($method, $uri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId, groups: $groups),
            ],
        ]);
    }

    /**
     * @param string[] $groups
     */
    private function getUserDefaultId(string $userId, array $groups = []): ?string
    {
        $response = $this->apiAs('GET', '/profiles/user-default', $userId, $groups);
        if (404 === $response->getStatusCode()) {
            return null;
        }
        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));

        return $response->toArray()['id'];
    }

    private function createProfile(string $name, string $ownerId = self::ADMIN, bool $public = false, bool $default = true, ?string $createdAt = null): Profile
    {
        $em = self::getEntityManager();
        $profile = new Profile();
        $profile->setName($name);
        $profile->setOwnerId($ownerId);
        $profile->setPublic($public);
        $profile->setDefaultProfile($default);
        if (null !== $createdAt) {
            new \ReflectionProperty(Profile::class, 'createdAt')->setValue($profile, new \DateTimeImmutable($createdAt));
        }
        $em->persist($profile);
        $em->flush();

        return $profile;
    }

    public function testAnonymousIsUnauthorized(): void
    {
        $this->createProfile('P', public: true);

        $this->assertStatus(401, 'GET', '/profiles/user-default', null);
    }

    public function testSettingTheFlagRequiresTheCapability(): void
    {
        $profile = $this->createProfile('Mine', self::USER, default: false);
        $uri = '/profiles/'.$profile->getId();

        $this->assertStatus(403, 'PATCH', $uri, self::USER, ['defaultProfile' => true]);
        // Unchanged: no capability needed (e.g. renaming it)
        $renamed = $this->apiJson('PATCH', $uri, self::USER, ['name' => 'Renamed', 'defaultProfile' => false]);
        $this->assertFalse($renamed['defaultProfile']);

        $this->grantOnType(self::USER, Profile::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_ASSIGN_DEFAULT_PROFILE);
        $this->assertTrue($this->apiJson('PATCH', $uri, self::USER, ['defaultProfile' => true])['defaultProfile']);
        $this->assertTrue($this->apiJson('GET', $uri, self::USER)['defaultProfile']);
        $this->assertFalse($this->apiJson('PATCH', $uri, self::USER, ['defaultProfile' => false])['defaultProfile']);
    }

    public function testSettingTheFlagRequiresEditingTheProfile(): void
    {
        $this->grantOnType(self::USER, Profile::OBJECT_TYPE, DataboxExtraPermissionInterface::PERM_ASSIGN_DEFAULT_PROFILE);
        $public = $this->createProfile('Public of other', self::OTHER, public: true, default: false);

        $this->assertStatus(403, 'PATCH', '/profiles/'.$public->getId(), self::USER, ['defaultProfile' => true]);
    }

    public function testNoDefault(): void
    {
        $this->createProfile('Not default', self::USER, default: false);
        $this->createProfile('Private of other', self::OTHER);

        $this->assertNull($this->getUserDefaultId(self::USER));
    }

    public function testAppliesToTheReaders(): void
    {
        $profile = $this->createProfile('Group');
        $this->grantGroupOnObject(self::GROUP, $profile, PermissionInterface::VIEW);

        $this->assertSame($profile->getId(), $this->getUserDefaultId(self::USER, [self::GROUP]));
        $this->assertNull($this->getUserDefaultId(self::USER, [self::OTHER_GROUP]));
        // The owner reads it
        $this->assertSame($profile->getId(), $this->getUserDefaultId(self::ADMIN));
    }

    public function testSharedWinsOverPublic(): void
    {
        $public = $this->createProfile('Public', public: true, createdAt: '2026-01-01 00:00:00');
        $this->assertSame($public->getId(), $this->getUserDefaultId(self::USER, [self::GROUP]));

        $group = $this->createProfile('Group', createdAt: '2026-02-01 00:00:00');
        $this->grantGroupOnObject(self::GROUP, $group, PermissionInterface::VIEW);
        $this->assertSame($group->getId(), $this->getUserDefaultId(self::USER, [self::GROUP]));
        $this->assertSame($public->getId(), $this->getUserDefaultId(self::USER, [self::OTHER_GROUP]));
    }

    public function testTheOldestWins(): void
    {
        $newer = $this->createProfile('Newer', public: true, createdAt: '2026-02-01 00:00:00');
        $older = $this->createProfile('Older', public: true, createdAt: '2026-01-01 00:00:00');

        $this->assertSame($older->getId(), $this->getUserDefaultId(self::USER));

        $older = self::getEntityManager()->find(Profile::class, $older->getId());
        $older->setDefaultProfile(false);
        self::getEntityManager()->flush();
        $this->assertSame($newer->getId(), $this->getUserDefaultId(self::USER));
    }
}
