<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Expose;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * An in-memory Expose API, to plug into a MockHttpClient: the publications, their assets
 * and sub-definitions, and the uploads (presigned PUTs included).
 */
final class FakeExposeApi
{
    public const string BASE_URL = 'https://api-expose.phrasea.test';
    private const string STORAGE_URL = 'https://storage.phrasea.test';

    /**
     * @var array<string, array{id: string, title: string, editable: bool, data: array}>
     */
    public array $publications = [];

    /**
     * @var array<string, array{id: string, publicationId: string, assetId: ?string, title: ?string, description: ?string, translations: ?array, clientAnnotations: ?string, uploaded: bool}>
     */
    public array $assets = [];

    /**
     * @var array<string, array{id: string, assetId: string, name: string, clientAnnotations: ?string}>
     */
    public array $subDefinitions = [];

    /**
     * Every request received, as "METHOD /path" (the storage requests as "PUT storage").
     *
     * @var string[]
     */
    public array $requests = [];

    /**
     * Status codes answered to the API requests matching "METHOD /path" (or "*" for all of them).
     *
     * @var array<string, int>
     */
    public array $failures = [];

    /**
     * The files announced to "POST /uploads", in order.
     *
     * @var list<array{filename: ?string, type: ?string}>
     */
    public array $uploads = [];

    /**
     * @var (callable(string, ?string): ?string)|null
     */
    private $uploadRefuser;

    public function createClient(): MockHttpClient
    {
        return new MockHttpClient($this, self::BASE_URL);
    }

    /**
     * Refuses the uploads for which $refuser returns a reason, as the file validation of Expose does
     * (a 400 answer whose "detail" is the reason).
     *
     * @param callable(string $filename, ?string $type): ?string $refuser
     */
    public function refuseUploads(callable $refuser): void
    {
        $this->uploadRefuser = $refuser;
    }

    public function addPublication(string $title, bool $editable = true): string
    {
        $id = Uuid::uuid4()->toString();
        $this->publications[$id] = [
            'id' => $id,
            'title' => $title,
            'editable' => $editable,
            'data' => [],
        ];

        return $id;
    }

    /**
     * Publishes an asset as another client would.
     */
    public function addAsset(string $publicationId, array $clientAnnotations, ?string $title = null): string
    {
        $id = Uuid::uuid4()->toString();
        $this->assets[$id] = [
            'id' => $id,
            'publicationId' => $publicationId,
            'assetId' => null,
            'title' => $title,
            'description' => null,
            'translations' => null,
            'clientAnnotations' => json_encode($clientAnnotations, JSON_THROW_ON_ERROR),
            'uploaded' => true,
        ];

        return $id;
    }

    /**
     * @return array<string, array> the assets of a publication, indexed by ID
     */
    public function getPublicationAssets(string $publicationId): array
    {
        return array_filter($this->assets, fn (array $asset): bool => $asset['publicationId'] === $publicationId);
    }

    /**
     * @return array<string, array> the sub-definitions of an asset, indexed by name
     */
    public function getSubDefinitions(string $assetId): array
    {
        $subDefinitions = [];
        foreach ($this->subDefinitions as $subDefinition) {
            if ($subDefinition['assetId'] === $assetId) {
                $subDefinitions[$subDefinition['name']] = $subDefinition;
            }
        }

        return $subDefinitions;
    }

    /**
     * @return string[] the requests changing something ("METHOD /path" with IDs replaced by "{id}"), the uploads excluded
     */
    public function getWrites(): array
    {
        $writes = [];
        foreach ($this->requests as $request) {
            if (str_starts_with($request, 'GET ') || str_starts_with($request, 'PUT storage') || str_contains($request, '/uploads')) {
                continue;
            }
            $writes[] = preg_replace('#/[0-9a-f]{8}-[0-9a-f-]{27}#', '/{id}', $request);
        }

        return $writes;
    }

    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        if (str_starts_with($url, self::STORAGE_URL)) {
            $this->requests[] = 'PUT storage';

            return new MockResponse('', ['http_code' => 200, 'response_headers' => ['ETag' => '"etag"']]);
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $request = $method.' '.$path;
        $this->requests[] = $request;

        $failure = $this->failures[$request] ?? $this->failures['*'] ?? null;
        if (null !== $failure) {
            return self::json(['detail' => 'Failure'], $failure);
        }

        $body = isset($options['body']) && is_string($options['body']) && '' !== $options['body']
            ? json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR)
            : [];
        $segments = explode('/', trim($path, '/'));

        return match (true) {
            'POST' === $method && '/publications' === $path => $this->createPublication($body),
            'GET' === $method && 2 === count($segments) && 'publications' === $segments[0] => $this->getPublication($segments[1]),
            'POST' === $method && '/uploads' === $path => $this->createUpload($body),
            'POST' === $method && '/assets' === $path => $this->createAsset($body),
            'PATCH' === $method && 'assets' === $segments[0] => $this->updateAsset($segments[1], $body),
            'DELETE' === $method && 'assets' === $segments[0] => $this->deleteAsset($segments[1]),
            'POST' === $method && '/sub-definitions' === $path => $this->createSubDefinition($body),
            'DELETE' === $method && 'sub-definitions' === $segments[0] => $this->deleteSubDefinition($segments[1]),
            default => self::json(['detail' => sprintf('No route for "%s"', $request)], 404),
        };
    }

    private function createPublication(array $body): MockResponse
    {
        if ('' === trim((string) ($body['title'] ?? ''))) {
            return self::json(['detail' => 'title: This value should not be blank.'], 422);
        }

        $id = $this->addPublication((string) ($body['title'] ?? ''));
        $this->publications[$id]['data'] = $body;

        return self::json(['id' => $id, 'title' => $this->publications[$id]['title']], 201);
    }

    private function getPublication(string $id): MockResponse
    {
        $publication = $this->publications[$id] ?? null;
        if (null === $publication) {
            return self::json(['detail' => 'Not Found'], 404);
        }

        $assets = [];
        foreach ($this->getPublicationAssets($id) as $asset) {
            $subDefinitions = [];
            foreach ($this->getSubDefinitions($asset['id']) as $subDefinition) {
                $subDefinitions[] = [
                    'id' => $subDefinition['id'],
                    'name' => $subDefinition['name'],
                ] + ($publication['editable'] ? ['clientAnnotations' => $subDefinition['clientAnnotations']] : []);
            }

            $assets[] = [
                'id' => $asset['id'],
                'assetId' => $asset['assetId'],
                'title' => $asset['title'],
                'description' => $asset['description'],
                'translations' => $asset['translations'],
                'subDefinitions' => $subDefinitions,
            ] + ($publication['editable'] ? ['clientAnnotations' => $asset['clientAnnotations']] : []);
        }

        return self::json([
            'id' => $id,
            'title' => $publication['title'],
            'capabilities' => [
                'edit' => $publication['editable'],
                'delete' => $publication['editable'],
                'operator' => false,
            ],
            'assets' => $assets,
        ]);
    }

    private function createUpload(array $body): MockResponse
    {
        $filename = isset($body['filename']) ? (string) $body['filename'] : null;
        $type = isset($body['type']) ? (string) $body['type'] : null;
        $this->uploads[] = ['filename' => $filename, 'type' => $type];

        if (null !== $this->uploadRefuser && null !== $reason = ($this->uploadRefuser)((string) $filename, $type)) {
            return self::json(['detail' => $reason], 400);
        }

        $id = Uuid::uuid4()->toString();

        return self::json([
            'id' => $id,
            'chunkSize' => 5 * 1024 * 1024,
            'urls' => ['1' => self::STORAGE_URL.'/uploads/'.$id.'/1'],
        ], 201);
    }

    private function createAsset(array $body): MockResponse
    {
        $publicationId = $body['publication_id'] ?? null;
        if (!isset($this->publications[$publicationId])) {
            return self::json(['detail' => 'Publication not found'], 404);
        }

        $id = Uuid::uuid4()->toString();
        $this->assets[$id] = [
            'id' => $id,
            'publicationId' => $publicationId,
            'assetId' => $body['asset_id'] ?? null,
            'title' => $body['title'] ?? null,
            'description' => $body['description'] ?? null,
            'translations' => $body['translations'] ?? null,
            'clientAnnotations' => $body['clientAnnotations'] ?? null,
            'uploaded' => isset($body['multipart']['uploadId']),
        ];

        return self::json(['id' => $id], 201);
    }

    private function updateAsset(string $id, array $body): MockResponse
    {
        if (!isset($this->assets[$id])) {
            return self::json(['detail' => 'Not Found'], 404);
        }

        foreach (['title', 'description', 'translations', 'clientAnnotations'] as $key) {
            if (array_key_exists($key, $body)) {
                $this->assets[$id][$key] = $body[$key];
            }
        }

        return self::json(['id' => $id]);
    }

    private function deleteAsset(string $id): MockResponse
    {
        if (!isset($this->assets[$id])) {
            return self::json(['detail' => 'Not Found'], 404);
        }

        unset($this->assets[$id]);
        foreach ($this->subDefinitions as $subDefinitionId => $subDefinition) {
            if ($subDefinition['assetId'] === $id) {
                unset($this->subDefinitions[$subDefinitionId]);
            }
        }

        return new MockResponse('', ['http_code' => 204]);
    }

    private function createSubDefinition(array $body): MockResponse
    {
        $assetId = $body['asset_id'] ?? null;
        if (!isset($this->assets[$assetId])) {
            return self::json(['detail' => 'Asset not found'], 404);
        }

        $id = Uuid::uuid4()->toString();
        $this->subDefinitions[$id] = [
            'id' => $id,
            'assetId' => $assetId,
            'name' => (string) ($body['name'] ?? ''),
            'clientAnnotations' => $body['clientAnnotations'] ?? null,
        ];

        return self::json(['id' => $id, 'uploadURL' => self::STORAGE_URL.'/sub-definitions/'.$id], 201);
    }

    private function deleteSubDefinition(string $id): MockResponse
    {
        if (!isset($this->subDefinitions[$id])) {
            return self::json(['detail' => 'Not Found'], 404);
        }

        unset($this->subDefinitions[$id]);

        return new MockResponse('', ['http_code' => 204]);
    }

    private static function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
    }
}
