<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Workspace;
use App\Tests\AbstractDataboxTestCase;

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

    public function testPdfProvidedTerms(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(Workspace::class, ['slug' => 'test-workspace']);

        $dataUri = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 fake terms pdf');

        // Provide terms directly as a PDF
        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'termsPdf' => $dataUri,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['terms']['version']);
        $this->assertNull($data['terms']['text'] ?? null);
        $this->assertNotEmpty($data['terms']['pdfUrl']);

        // Same PDF again: no new version
        $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'termsPdf' => $dataUri,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $response = $client->request('GET', $iri, [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertSame(1, $response->toArray()['terms']['version']);

        // A different PDF creates a new version
        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'termsPdf' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 updated terms pdf'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $response->toArray()['terms']['version']);

        // Non-PDF payload is rejected
        $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'termsPdf' => 'data:application/pdf;base64,'.base64_encode('not a pdf'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Removing the PDF (no text behind) clears the terms
        $response = $client->request('PUT', $iri, [
            'headers' => $this->adminHeaders(),
            'json' => [
                'termsPdf' => '',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayNotHasKey('pdfUrl', $data['terms'] ?? []);
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
