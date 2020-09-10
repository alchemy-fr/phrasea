<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use DateInterval;
use DateTime;
use Symfony\Component\PropertyAccess\PropertyAccess;

class PublicationTest extends AbstractExposeTestCase
{
    public function testCreatePublicationOK(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
            ],
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals('Foo', $json['title']);
        $this->assertEquals('123', $json['ownerId']);
        $this->assertArrayHasKey('config', $json);
        $this->assertEquals('download', $json['config']['layout']);
        $this->assertRegExp('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
    }

    public function testListPublications(): void
    {
        $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publiclyListed' => true,
        ]);
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publiclyListed' => true,
        ]);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications', []);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertEquals(2, count($json));
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #2', $json[1]['title']);
    }

    public function testCreatePublicationWithoutTitleWillGenerate400(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publications', []);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetPublicationFromAdmin(): void
    {
        $id = $this->createPublication();
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals(null, $json['ownerId']);
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
            $this->assertEquals(json_encode($expectedValue), json_encode($propertyAccessor->getValue($publication, $propertyPath)));
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
                    'enabled' => false,
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
        $id = $this->createPublication(['enabled' => true]);
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayNotHasKey('ownerId', $json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }

    public function testGetNonEnabledPublicationFromAdmin(): void
    {
        $id = $this->createPublication(['enabled' => false]);
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertEquals(null, $json['ownerId']);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }

    public function testGetNonEnabledPublicationFromAnonymous(): void
    {
        $id = $this->createPublication(['enabled' => false]);
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
            $startDate = new DateTime();
            $startDate->add(DateInterval::createFromDateString($start));
            $options['startDate'] = $startDate;
        }
        if (null !== $end) {
            $endDate = new DateTime();
            $endDate->add(DateInterval::createFromDateString($end));
            $options['endDate'] = $endDate;
        }
        $id = $this->createPublication($options);
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
            $startDate = new DateTime();
            $startDate->add(DateInterval::createFromDateString($start));
            $options['startDate'] = $startDate;
        }
        if (null !== $end) {
            $endDate = new DateTime();
            $endDate->add(DateInterval::createFromDateString($end));
            $options['endDate'] = $endDate;
        }
        $id = $this->createPublication($options);
        $response = $this->request(null, 'GET', '/publications');
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($shouldBeVisible ? 1 : 0, count($json));
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
        $id = $this->createPublication();
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'DELETE', '/publications/'.$id);
        $this->assertEquals(204, $response->getStatusCode());
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPutPublication(): void
    {
        $id = $this->createPublication([
            'publiclyListed' => true,
            'enabled' => false,
        ]);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'PUT', '/publications/'.$id, [
            'title' => 'Foo',
            'config' => [
                'enabled' => true,
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json['config']['enabled']);
        $this->assertEquals(true, $json['config']['publiclyListed']);
    }

    public function testDeletePublicationAsAnonymous(): void
    {
        $id = $this->createPublication();
        $response = $this->request(null, 'DELETE', '/publications/'.$id);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeletePublicationAsUser(): void
    {
        $id = $this->createPublication();
        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'DELETE', '/publications/'.$id);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPublicationWillHaveSafeHtmlDescription(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publications', [
            'title' => 'Foo',
            'description' => <<<DESC
<div><a onclick="alert('ok')">B</a></div>
DESC
            ,
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(201, $response->getStatusCode());

        $this->assertArrayHasKey('description', $json);
        $this->assertEquals('<div><a>B</a></div>', $json['description']);
    }
}
