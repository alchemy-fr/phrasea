<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\PropertyAccess;

class PublicationTest extends AbstractExposeTestCase
{
    public function testCreatePublicationAsAdmin(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
            ],
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals('Foo', $json['title']);
        $this->assertEquals(KeycloakClientTestMock::ADMIN_UID, $json['ownerId']);
        $this->assertArrayHasKey('config', $json);
        $this->assertEquals('download', $json['config']['layout']);
        $this->assertMatchesUuid($json['id']);
    }

    public function testCreatePublicationAsUser(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
            ],
        ]);
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'objectType' => 'publication',
            'mask' => PermissionInterface::CREATE,
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
            ],
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals('Foo', $json['title']);
        $this->assertEquals(KeycloakClientTestMock::USER_UID, $json['ownerId']);
        $this->assertArrayHasKey('config', $json);
        $this->assertEquals('download', $json['config']['layout']);
        $this->assertMatchesUuid($json['id']);
    }

    public function testCreatePublicationAsUserWithoutPermissions(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
            ],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListPublications(): void
    {
        $pub1 = $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publiclyListed' => true,
        ])->getId();
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publiclyListed' => false,
        ]);
        $this->createPublication([
            'title' => 'Pub #3',
            'enabled' => true,
            'publiclyListed' => true,
        ]);
        $this->createPublication([
            'title' => 'Pub #4',
            'enabled' => false,
            'publiclyListed' => true,
        ]);
        $this->createPublication([
            'title' => 'Pub #1.1',
            'enabled' => true,
            'publiclyListed' => true,
            'parent_id' => $pub1,
        ]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertCount(2, $json);
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #3', $json[1]['title']);
    }

    public function testUserCanListItsDisabledPublications(): void
    {
        $pub1 = $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publiclyListed' => true,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ])->getId();
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publiclyListed' => false,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $this->createPublication([
            'title' => 'Pub #3',
            'enabled' => false,
            'publiclyListed' => true,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $this->createPublication([
            'title' => 'Pub #1.1',
            'enabled' => true,
            'publiclyListed' => true,
            'parent_id' => $pub1,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(3, $json);
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #2', $json[1]['title']);
        $this->assertEquals('Pub #3', $json[2]['title']);
    }

    public function testListFlattenPublications(): void
    {
        $pub1 = $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publiclyListed' => true,
        ])->getId();
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publiclyListed' => false,
        ])->getId();
        $this->createPublication([
            'title' => 'Pub #3',
            'enabled' => true,
            'publiclyListed' => true,
        ])->getId();
        $this->createPublication([
            'title' => 'Pub #1.1',
            'enabled' => true,
            'publiclyListed' => true,
            'parent_id' => $pub1,
        ])->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $json);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications?flatten=true');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $json);
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #1.1', $json[1]['title']);
        $this->assertEquals('Pub #3', $json[2]['title']);
    }

    public function testListPublicationsAsAdmin(): void
    {
        $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publiclyListed' => true,
        ]);
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publiclyListed' => false,
        ]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertCount(2, $json);
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #2', $json[1]['title']);
    }

    public function testListPublicationsAsOwner(): void
    {
        $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publiclyListed' => true,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publiclyListed' => false,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertCount(2, $json);
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #2', $json[1]['title']);
    }

    public function testICanUnsetBeginAtDateOnPublication(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'beginsAt' => '2042-12-12',
            ],
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('2042-12-12T00:00:00+00:00', $json['config']['beginsAt']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/publications/'.$json['id'], [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
                'beginsAt' => null,
            ],
        ]);

        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertArrayNotHasKey('beginsAt', $json['config']);
    }

    public function testCreatePublicationWithoutTitleWillGenerate400(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetPublicationFromAdmin(): void
    {
        $publication = $this->createPublication([
            'ownerId' => 'user42',
        ]);
        $id = $publication->getId();
        $this->createAsset($publication);
        $this->createAsset($publication);

        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('assets', $json);
        $this->assertCount(2, $json['assets']);
        $this->assertArrayHasKey('id', $json['assets'][0]);
        $this->assertNotNull($json['assets'][0]['id']);
        $this->assertEquals('user42', $json['ownerId']);
    }

    public function testGetPublicationWithSlug(): void
    {
        $publication = $this->createPublication([
            'slug' => 'foo',
        ]);
        $this->createAsset($publication);
        $this->createAsset($publication);

        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/foo');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('assets', $json);
        $this->assertCount(2, $json['assets']);
        $this->assertArrayHasKey('id', $json['assets'][0]);
        $this->assertNotNull($json['assets'][0]['id']);
    }

    /**
     * @dataProvider publicationAndProfilesProvider
     */
    public function testPublicationConfig(array $publicationOptions, array $profileOptions, array $expectations): void
    {
        $profile = new PublicationProfile();
        $this->configureProfile($profile, $profileOptions);
        $publication = new Publication();
        $this->configurePublication($publication, array_merge(
            $publicationOptions,
            [
                'profile' => $profile,
            ]
        ));

        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($expectations as $propertyPath => $expectedValue) {
            $this->assertEquals(json_encode($expectedValue, JSON_THROW_ON_ERROR), json_encode($propertyAccessor->getValue($publication, $propertyPath), JSON_THROW_ON_ERROR));
        }
    }

    public function publicationAndProfilesProvider(): array
    {
        return [
            [
                [],
                [],
                [
                    'enabled' => true,
                ],
            ],
            [
                [
                    'enabled' => false,
                ],
                [],
                [
                    'enabled' => false,
                ],
            ],

            [
                [
                    'enabled' => true,
                ],
                [
                    'enabled' => false,
                ],
                [
                    'enabled' => true,
                ],
            ],

            [
                [
                    'enabled' => false,
                ],
                [
                    'enabled' => false,
                ],
                [
                    'enabled' => false,
                    'securityMethod' => null,
                ],
            ],

            [
                [
                    'enabled' => true,
                ],
                [
                    'enabled' => null,
                ],
                [
                    'enabled' => true,
                ],
            ],

            [
                [
                    'enabled' => null,
                ],
                [
                    'enabled' => true,
                ],
                [
                    'enabled' => true,
                ],
            ],

            [
                [
                    'enabled' => null,
                ],
                [
                    'enabled' => false,
                ],
                [
                    'enabled' => false,
                ],
            ],

            [
                [
                    'enabled' => null,
                ],
                [
                    'enabled' => null,
                ],
                [
                    'enabled' => false,
                ],
            ],

            [
                [
                    'enabled' => false,
                ],
                [
                    'enabled' => null,
                ],
                [
                    'enabled' => false,
                ],
            ],

            [
                [
                    'password' => 'xxx',
                ],
                [
                ],
                [
                    'securityMethod' => 'password',
                ],
            ],

            [
                [
                ],
                [
                    'password' => 'xxx',
                ],
                [
                    'securityMethod' => 'password',
                ],
            ],

            [
                [
                    'css' => '.publication {}',
                ],
                [
                    'css' => '.profile {}',
                ],
                [
                    'css' => '.publication {}'."\n".'.profile {}',
                ],
            ],

            [
                [
                    'copyrightText' => 'Publication',
                ],
                [
                    'copyrightText' => 'Profile',
                ],
                [
                    'copyrightText' => 'Publication',
                ],
            ],

            [
                [
                ],
                [
                    'copyrightText' => 'Profile',
                ],
                [
                    'copyrightText' => 'Profile',
                ],
            ],

            [
                [
                    'mapOptions' => ['lat' => 2.31],
                ],
                [
                    'mapOptions' => ['lat' => 2.32],
                ],
                [
                    'mapOptions' => ['lat' => 2.31],
                ],
            ],

            [
                [
                    'mapOptions' => [],
                ],
                [
                    'mapOptions' => [],
                ],
                [
                    'mapOptions' => [],
                ],
            ],

            [
                [
                    'mapOptions' => [],
                ],
                [
                    'mapOptions' => ['lat' => 2.32],
                ],
                [
                    'mapOptions' => ['lat' => 2.32],
                ],
            ],

            [
                [
                    'mapOptions' => ['lat' => 2.31],
                ],
                [
                ],
                [
                    'mapOptions' => ['lat' => 2.31],
                ],
            ],
        ];
    }

    public function testGetPublicationFromAnonymous(): void
    {
        $id = $this->createPublication(['enabled' => true])->getId();
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayNotHasKey('ownerId', $json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }

    public function testGetNonEnabledPublicationFromAdmin(): void
    {
        $id = $this->createPublication([
            'enabled' => false,
            'ownerId' => 'user42',
        ])->getId();
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertEquals('user42', $json['ownerId']);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }

    public function testGetNonEnabledPublicationFromAnonymous(): void
    {
        $id = $this->createPublication(['enabled' => false])->getId();
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * @dataProvider getPublicationVisibilityData
     */
    public function testPublicationVisibility(bool $enabled, ?string $start, ?string $end, bool $shouldBeVisible): void
    {
        $options = [
            'enabled' => $enabled,
        ];
        if (null !== $start) {
            $options['startDate'] = (new \DateTimeImmutable())->add(\DateInterval::createFromDateString($start));
        }
        if (null !== $end) {
            $options['endDate'] = (new \DateTimeImmutable())->add(\DateInterval::createFromDateString($end));
        }
        $id = $this->createPublication($options)->getId();
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $this->assertEquals($shouldBeVisible ? 200 : 401, $response->getStatusCode());
    }

    public function getPublicationVisibilityData(): array
    {
        return [
            [false, null, null, false],
            [true, null, null, true],
            [true, '-1 day', null, true],
            [true, '-1 day', '+1 day', true],
            [true, '+1 day', '+2 day', false],
            [true, '-2 day', '-1 day', false],
        ];
    }

    /**
     * @dataProvider getPublicationPubliclyListedData
     */
    public function testPublicationPubliclyListed(bool $listed, bool $enabled, ?string $start, ?string $end, bool $shouldBeVisible): void
    {
        $options = [
            'enabled' => $enabled,
            'publiclyListed' => $listed,
        ];
        if (null !== $start) {
            $options['startDate'] = (new \DateTimeImmutable())->add(\DateInterval::createFromDateString($start));
        }
        if (null !== $end) {
            $options['endDate'] = (new \DateTimeImmutable())->add(\DateInterval::createFromDateString($end));
        }
        $this->createPublication($options);
        $response = $this->request(null, 'GET', '/publications');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount($shouldBeVisible ? 1 : 0, $json);
    }

    public function getPublicationPubliclyListedData(): array
    {
        return [
            [true, false, null, null, false],
            [true, false, null, null, false],
            [true, true, null, null, true],
            [false, true, null, null, false],
            [true, true, '-1 day', null, true],
            [false, true, '-1 day', null, false],
            [true, true, '-1 day', '+1 day', true],
            [false, true, '-1 day', '+1 day', false],
            [true, true, '+1 day', '+2 day', false],
            [false, true, '+1 day', '+2 day', false],
            [true, true, '-2 day', '-1 day', false],
            [false, true, '-2 day', '-1 day', false],
        ];
    }

    public function testDeletePublicationAsAdmin(): void
    {
        $id = $this->createPublication()->getId();
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'DELETE', '/publications/'.$id);
        $this->assertEquals(204, $response->getStatusCode());
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPutAsAdminPublication(): void
    {
        $id = $this->createPublication([
            'publiclyListed' => true,
            'enabled' => false,
        ])->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/publications/'.$id, [
            'title' => 'Foo',
            'config' => [
                'enabled' => true,
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(true, $json['config']['enabled']);
        $this->assertEquals(true, $json['config']['publiclyListed']);
    }

    public function testPutAsNonOwnerUserWillGet403Publication(): void
    {
        $id = $this->createPublication([
            'publiclyListed' => true,
            'enabled' => false,
        ])->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$id, [
            'title' => 'Foo',
            'config' => [
                'enabled' => true,
            ],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPutAsOwnerUserPublication(): void
    {
        $id = $this->createPublication([
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'publiclyListed' => true,
            'enabled' => false,
        ])->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$id, [
            'title' => 'Foo',
            'config' => [
                'enabled' => true,
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(true, $json['config']['enabled']);
        $this->assertEquals(true, $json['config']['publiclyListed']);
    }

    public function testChangeProfileOnPublication(): void
    {
        $publication = $this->createPublication([
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
            'publiclyListed' => true,
            'enabled' => false,
        ]);
        $publicationId = $publication->getId();

        $profileId = $this->createProfile([
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
            'publiclyListed' => true,
            'enabled' => false,
        ]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$publicationId, [
            'profile' => '/publication-profiles/'.$profileId,
        ]);
        // Cannot change profile of publication
        $this->assertEquals(403, $response->getStatusCode());

        $aclRes = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'objectType' => 'publication',
            'objectId' => $publicationId,
            'mask' => PermissionInterface::EDIT,
        ]);
        $this->assertEquals(200, $aclRes->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$publicationId, [
            'profile' => '/publication-profiles/'.$profileId,
        ]);
        // Still cannot change profile of publication with EDIT permission (need OPERATOR)
        $this->assertEquals(403, $response->getStatusCode());

        $aclRes = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'objectType' => 'publication',
            'objectId' => $publicationId,
            'mask' => PermissionInterface::OPERATOR + PermissionInterface::EDIT,
        ]);
        $this->assertEquals(200, $aclRes->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$publicationId, [
            'profile' => '/publication-profiles/'.$profileId,
        ]);
        // Cannot read this profile
        $this->assertEquals(403, $response->getStatusCode());

        $aclRes = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'objectType' => 'profile',
            'objectId' => $profileId,
            'mask' => PermissionInterface::VIEW,
        ]);
        $this->assertEquals(200, $aclRes->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$publicationId, [
            'profile' => '/publication-profiles/'.$profileId,
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals($profileId, $json['profile']['id']);
    }

    public function testPutAsOwnerUserPublicationProtectedWithPassword(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'objectType' => 'publication',
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'mask' => 2,
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'enabled' => false,
                'securityMethod' => 'password',
                'securityOptions' => [
                    'password' => '$3cr3t!',
                ],
            ],
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(false, $json['config']['enabled']);
        $id = $json['id'];

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$id, [
            'title' => 'Foo',
            'config' => [
                'enabled' => true,
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(true, $json['config']['enabled']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/assets', [
            'publication_id' => $id,
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        if (500 === $response->getStatusCode()) {
            var_dump($response->getContent());
        }
    }

    public function testUserWithACECanEditPublication(): void
    {
        $id = $this->createPublication([
            'title' => 'Pub',
            'enabled' => false,
        ])->getId();
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'objectType' => 'publication',
            'objectId' => $id,
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'mask' => 1 + 2 + 4,
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications/'.$id);
        if (500 === $response->getStatusCode()) {
            var_dump($response->getContent());
        }
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertEquals('Pub', $json['title']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/assets', [
            'publication_id' => $id,
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        if (500 === $response->getStatusCode()) {
            var_dump($response->getContent());
        }
        $this->assertEquals(201, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PUT', '/publications/'.$id, [
            'title' => 'Foo',
            'config' => [
                'enabled' => true,
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(true, $json['config']['enabled']);
    }

    public function testDeletePublicationAsAnonymous(): void
    {
        $id = $this->createPublication()->getId();
        $response = $this->request(null, 'DELETE', '/publications/'.$id);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeletePublicationAsUser(): void
    {
        $id = $this->createPublication()->getId();
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'DELETE', '/publications/'.$id);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPublicationWillHaveSafeHtmlDescription(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'description' => <<<DESC
<div><a onclick="alert('ok')">B</a></div>
DESC
            ,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(201, $response->getStatusCode());

        $this->assertArrayHasKey('description', $json);
        $this->assertEquals('<div><a>B</a></div>', $json['description']);
    }
}
