<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Values longer than their column must be rejected with a 422, not reach the database
 * (where PostgreSQL fails with a 500; SQLite, used by the tests, would silently store them).
 */
class ValidationTest extends AbstractExposeTestCase
{
    /**
     * @dataProvider getTooLongPublicationData
     */
    public function testCreatePublicationWithTooLongValue(array $data, string $propertyPath): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications', $data);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), $propertyPath);
    }

    public function getTooLongPublicationData(): array
    {
        return [
            [['title' => str_repeat('a', 256)], 'title'],
            [['title' => 'Foo', 'slug' => str_repeat('a', 101)], 'slug'],
            [['title' => 'Foo', 'config' => ['theme' => str_repeat('a', 31)]], 'config.theme'],
            [['title' => 'Foo', 'config' => ['layout' => str_repeat('a', 21)]], 'config.layout'],
            [['title' => 'Foo', 'config' => ['terms' => ['url' => str_repeat('a', 256)]]], 'config.terms.url'],
        ];
    }

    public function testCreatePublicationWithMaxLengthTitle(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications', [
            'title' => str_repeat('a', 255),
        ]);

        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
    }

    public function testDuplicateSlugIsReportedOnce(): void
    {
        $this->createPublication(['slug' => 'taken']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publications', [
            'title' => 'Foo',
            'slug' => 'taken',
        ]);

        $violations = $this->assertViolation($response->getStatusCode(), $response->getContent(), 'slug');
        $this->assertCount(1, $violations);
    }

    public function testUpdatePublicationWithTooLongTitle(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/publications/'.$publication->getId(), [
            'title' => str_repeat('a', 256),
        ]);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), 'title');
    }

    public function testCreatePublicationWithCircularParent(): void
    {
        $parent = $this->createPublication();
        $child = $this->createPublication(['parent' => $parent]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/publications/'.$parent->getId(), [
            'parent' => '/publications/'.$child->getId(),
        ]);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), 'parent');
    }

    /**
     * @dataProvider getTooLongProfileData
     */
    public function testCreateProfileWithTooLongValue(array $data, string $propertyPath): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/publication-profiles', $data);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), $propertyPath);
    }

    public function getTooLongProfileData(): array
    {
        return [
            [['name' => str_repeat('a', 151)], 'name'],
            [['name' => 'Foo', 'config' => ['theme' => str_repeat('a', 31)]], 'config.theme'],
            [['name' => 'Foo', 'config' => ['downloadTerms' => ['url' => str_repeat('a', 256)]]], 'config.downloadTerms.url'],
        ];
    }

    public function testUpdateAssetWithTooLongTitle(): void
    {
        $assetId = $this->createAsset($this->createPublication());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/assets/'.$assetId, [
            'title' => str_repeat('a', 256),
        ]);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), 'title');
    }

    public function testUploadAssetWithTooLongTitle(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/assets',
            [
                'publication_id' => $publication->getId(),
                'title' => str_repeat('a', 256),
            ], [
                'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
            ]);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), 'title');
        self::getEntityManager()->clear();
        $this->assertCount(0, $this->findPublicationAssets($publication->getId()));
    }

    public function testUploadSubDefinitionWithTooLongName(): void
    {
        $assetId = $this->createAsset($this->createPublication());

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/sub-definitions',
            [
                'asset_id' => $assetId,
                'name' => str_repeat('a', 31),
            ], [
                'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
            ]);

        $this->assertViolation($response->getStatusCode(), $response->getContent(), 'name');
    }

    /**
     * @dataProvider getInvalidEmailData
     */
    public function testDownloadRequestWithInvalidEmail(array $data): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            sprintf('/publications/%s/assets/%s/download-request', $publication->getId(), $assetId),
            $data,
        );

        $this->assertEquals(422, $response->getStatusCode(), $response->getContent());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringStartsWith('email: ', $json['detail']);
    }

    public function getInvalidEmailData(): array
    {
        return [
            [[]],
            [['email' => 'not-an-email']],
            [['email' => str_repeat('a', 250).'@example.com']],
        ];
    }

    /**
     * @return array<int, array{propertyPath: string, message: string}>
     */
    private function assertViolation(int $statusCode, string $content, string $propertyPath): array
    {
        $this->assertEquals(422, $statusCode, $content);
        $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $violations = array_values(array_filter(
            $json['violations'] ?? [],
            static fn (array $violation): bool => $violation['propertyPath'] === $propertyPath,
        ));
        $this->assertNotEmpty($violations, sprintf('No violation on "%s" in: %s', $propertyPath, $content));

        return $violations;
    }

    private function findPublicationAssets(string $publicationId): array
    {
        return self::getEntityManager()
            ->getRepository(\App\Entity\Asset::class)
            ->findBy(['publication' => $publicationId]);
    }
}
