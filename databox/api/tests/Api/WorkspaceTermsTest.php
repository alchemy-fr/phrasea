<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Tests\AbstractDataboxTestCase;
use Doctrine\ORM\EntityManagerInterface;

class WorkspaceTermsTest extends AbstractDataboxTestCase
{
    private function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
        ];
    }

    private function userHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
        ];
    }

    public function testTermsVersioningAndSignature(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        // Define terms (v1)
        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => 'You must credit the author.',
                'attachTermsToExports' => true,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('You must credit the author.', $data['terms']['text']);
        $this->assertSame(1, $data['terms']['version']);
        $this->assertTrue($data['terms']['attachToExports']);

        // User sees unsigned terms
        $response = $client->request('GET', $iri, [
            'headers' => $this->userHeaders(),
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertFalse($data['terms']['signed']);

        // User signs
        $response = $client->request('POST', $iri.'/terms/sign', [
            'headers' => $this->userHeaders(),
            'json' => [],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['terms']['signed']);

        // Unchanged content does not create a new version
        $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => 'You must credit the author.',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $response = $client->request('GET', $iri, [
            'headers' => $this->userHeaders(),
        ]);
        $data = $response->toArray();
        $this->assertSame(1, $data['terms']['version']);
        $this->assertTrue($data['terms']['signed']);

        // Content change creates a new version: signature must be renewed
        $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => 'You must credit the author. No commercial use.',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $response = $client->request('GET', $iri, [
            'headers' => $this->userHeaders(),
        ]);
        $data = $response->toArray();
        $this->assertSame(2, $data['terms']['version']);
        $this->assertFalse($data['terms']['signed']);

        // Re-sign the new version
        $response = $client->request('POST', $iri.'/terms/sign', [
            'headers' => $this->userHeaders(),
            'json' => [],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['terms']['signed']);
    }

    /**
     * Simulates a finished S3 multipart upload: the file is already in the
     * storage and the MultipartUpload row is complete, so the endpoint does
     * not need to reach S3. Returns the "multipart" request payload.
     */
    private function createCompletedUpload(string $content, string $name, string $type): array
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        /** @var FileStorageManager $storage */
        $storage = $container->get(FileStorageManager::class);

        $upload = new MultipartUpload();
        $upload->setFilename($name);
        $upload->setType($type);
        $upload->setSize(strlen($content));
        $upload->setPath(sprintf('test-uploads/%s.%s', uniqid(), pathinfo($name, PATHINFO_EXTENSION)));
        $upload->setUploadId('test-'.uniqid());
        $upload->setComplete(true);
        $em->persist($upload);
        $em->flush();

        $storage->store($upload->getPath(), $content);

        return [
            'uploadId' => $upload->getId(),
            'parts' => [
                ['ETag' => 'test-etag', 'PartNumber' => 1],
            ],
        ];
    }

    public function testPdfProvidedTerms(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        // Provide terms directly as a PDF (multipart upload)
        $response = $client->request('POST', $iri.'/terms', [
            'headers' => $this->adminHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload('%PDF-1.4 fake terms pdf', 'terms.pdf', 'application/pdf'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['terms']['version']);
        $this->assertNull($data['terms']['text'] ?? null);
        $this->assertNotEmpty($data['terms']['pdfUrl']);

        // Same PDF again: no new version
        $client->request('POST', $iri.'/terms', [
            'headers' => $this->adminHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload('%PDF-1.4 fake terms pdf', 'terms.pdf', 'application/pdf'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $response = $client->request('GET', $iri, [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertSame(1, $response->toArray()['terms']['version']);

        // A different PDF creates a new version
        $response = $client->request('POST', $iri.'/terms', [
            'headers' => $this->adminHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload('%PDF-1.4 updated terms pdf', 'terms.pdf', 'application/pdf'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $response->toArray()['terms']['version']);

        // Non-PDF payload is rejected
        $client->request('POST', $iri.'/terms', [
            'headers' => $this->adminHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload('not a pdf', 'terms.pdf', 'application/pdf'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Non-editor cannot upload
        $client->request('POST', $iri.'/terms', [
            'headers' => $this->userHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload('%PDF-1.4 x', 'terms.pdf', 'application/pdf'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        // Removing the PDF (no text behind) clears the terms
        $client->request('DELETE', $iri.'/terms', [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $response = $client->request('GET', $iri, [
            'headers' => $this->adminHeaders(),
        ]);
        $data = $response->toArray();
        $this->assertArrayNotHasKey('pdfUrl', $data['terms'] ?? []);
    }

    public function testWorkspaceLogoUpload(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $response = $client->request('POST', $iri.'/logo', [
            'headers' => $this->adminHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload($png, 'logo.png', 'image/png'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertNotEmpty($response->toArray()['logo']);

        // Non-image is rejected
        $client->request('POST', $iri.'/logo', [
            'headers' => $this->adminHeaders(),
            'json' => [
                'multipart' => $this->createCompletedUpload('plain text', 'logo.txt', 'text/plain'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Remove the logo
        $client->request('DELETE', $iri.'/logo', [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $response = $client->request('GET', $iri, [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertArrayNotHasKey('logo', array_filter($response->toArray(), fn ($v) => null !== $v));
    }

    public function testTermsTranslations(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => 'English terms.',
                'termsTranslations' => [
                    'fr' => 'Termes français.',
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $response->toArray()['terms']['version']);

        // Text is resolved according to the requested locale
        $response = $client->request('GET', $iri, [
            'headers' => $this->userHeaders() + ['X-Data-Locale' => 'fr'],
        ]);
        $data = $response->toArray();
        $this->assertSame('Termes français.', $data['terms']['text']);
        $this->assertSame('English terms.', $data['terms']['rawText']);
        $this->assertSame(['fr' => 'Termes français.'], $data['terms']['translations']);

        $response = $client->request('GET', $iri, [
            'headers' => $this->userHeaders() + ['X-Data-Locale' => 'en'],
        ]);
        $this->assertSame('English terms.', $response->toArray()['terms']['text']);

        // Changing only a translation creates a new version
        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'termsTranslations' => [
                    'fr' => 'Termes français (mis à jour).',
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['terms']['version']);
        $this->assertSame('English terms.', $data['terms']['rawText']);
    }

    public function testTermsGateBlocksWorkspaceContentUntilSigned(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $wsIri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);
        $assetIri = $this->findIriBy(Asset::class, ['key' => 'foo']);

        // Before any terms: the asset is readable
        $client->request('GET', $assetIri, [
            'headers' => $this->userHeaders(),
        ]);
        $this->assertResponseIsSuccessful();

        // Admin defines terms
        $client->request('PUT', $wsIri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => 'You must accept these terms.',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Workspace content is now denied to the unsigned user…
        $client->request('GET', $assetIri, [
            'headers' => $this->userHeaders(),
        ]);
        $this->assertResponseStatusCodeSame(403);

        // …but the workspace (and its terms) can still be fetched to sign them
        $response = $client->request('GET', $wsIri, [
            'headers' => $this->userHeaders(),
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertFalse($data['terms']['signed']);
        $this->assertTrue($data['termsUnsigned']);

        // The workspace owner is not gated
        $client->request('GET', $assetIri, [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertResponseIsSuccessful();

        // Signing restores access
        $client->request('POST', $wsIri.'/terms/sign', [
            'headers' => $this->userHeaders(),
            'json' => [],
        ]);
        $this->assertResponseIsSuccessful();

        $client->request('GET', $assetIri, [
            'headers' => $this->userHeaders(),
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testSignWithoutTermsIsRejected(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        $client->request('POST', $iri.'/terms/sign', [
            'headers' => $this->userHeaders(),
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testClearingTermsRemovesThem(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => 'Some terms.',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'terms' => '',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayNotHasKey('text', $data['terms'] ?? []);
    }
}
