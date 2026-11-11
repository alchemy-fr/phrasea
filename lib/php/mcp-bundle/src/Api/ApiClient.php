<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Api;

use Mcp\Exception\ToolCallException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Runs the MCP tools through the REST API as internal sub-requests, so that they get the very same
 * security (voters, ACL, Doctrine extensions), validation, persistence and normalization as the
 * API clients. The firewall only authenticates main requests: sub-requests inherit the token of
 * the MCP request.
 */
final readonly class ApiClient
{
    public function __construct(
        private HttpKernelInterface $httpKernel,
        private RequestStack $requestStack,
        private string $apiName = 'Phrasea',
    ) {
    }

    public function get(string $path, array $query = []): mixed
    {
        return $this->request(Request::METHOD_GET, $path, $query);
    }

    /**
     * @param list<string> $extraKeys top-level keys of the collection response to return besides the items (e.g. "facets")
     *
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}&array<string, mixed>
     */
    public function getCollection(string $path, array $query = [], array $extraKeys = []): array
    {
        $data = $this->request(Request::METHOD_GET, $path, $query, format: 'application/ld+json');

        $page = (int) ($query['page'] ?? 1);

        $result = [
            'items' => array_values($data['member'] ?? []),
            'totalItems' => $data['totalItems'] ?? null,
            'page' => $page,
            'hasNextPage' => isset($data['view']['next']),
        ];
        foreach ($extraKeys as $key) {
            if (is_array($data) && array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }

        return $result;
    }

    public function post(string $path, array $body = []): array
    {
        return (array) $this->request(Request::METHOD_POST, $path, body: $body);
    }

    public function put(string $path, array $body = []): array
    {
        return (array) $this->request(Request::METHOD_PUT, $path, body: $body);
    }

    public function patch(string $path, array $body): array
    {
        return (array) $this->request(Request::METHOD_PATCH, $path, body: $body);
    }

    public function delete(string $path): void
    {
        $this->request(Request::METHOD_DELETE, $path);
    }

    /**
     * Drops the arguments the model left unset, so that they do not overwrite the current values.
     */
    public static function withoutNulls(array $data): array
    {
        return array_filter($data, static fn (mixed $v): bool => null !== $v);
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        string $format = 'application/json',
    ): mixed {
        $mainRequest = $this->requestStack->getMainRequest();

        $server = [
            'HTTP_ACCEPT' => $format,
        ];
        if (null !== $mainRequest) {
            $server['HTTP_HOST'] = $mainRequest->getHttpHost();
            $server['HTTPS'] = $mainRequest->isSecure() ? 'on' : 'off';
            if ($mainRequest->headers->has('Authorization')) {
                $server['HTTP_AUTHORIZATION'] = $mainRequest->headers->get('Authorization');
            }
        }
        if (null !== $body) {
            $server['CONTENT_TYPE'] = Request::METHOD_PATCH === $method ? 'application/merge-patch+json' : 'application/json';
        }

        $subRequest = Request::create(
            $path.([] !== $query ? '?'.http_build_query($query) : ''),
            $method,
            server: $server,
            content: null !== $body ? json_encode($body, JSON_THROW_ON_ERROR) : null,
        );

        $response = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        $content = (string) $response->getContent();
        $data = '' !== $content ? json_decode($content, true) : null;

        if (!$response->isSuccessful()) {
            throw new ToolCallException(sprintf('%s API responded %d: %s', $this->apiName, $response->getStatusCode(), $this->extractErrorMessage($data) ?? $response->getStatusCode()));
        }

        return is_array($data) ? self::stripMetadataKeys($data) : $data;
    }

    private function extractErrorMessage(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        if (!empty($data['violations'])) {
            return implode('; ', array_map(
                static fn (array $v): string => ($v['propertyPath'] ? $v['propertyPath'].': ' : '').$v['message'],
                $data['violations'],
            ));
        }

        return $data['detail'] ?? $data['description'] ?? $data['message'] ?? $data['title'] ?? null;
    }

    /**
     * JSON-LD metadata ("@context", "@id", "@type") and debug data ("debug:…") are noise for a model.
     */
    private static function stripMetadataKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && (str_starts_with($key, '@') || str_starts_with($key, 'debug:'))) {
                continue;
            }
            $result[$key] = is_array($value) ? self::stripMetadataKeys($value) : $value;
        }

        return $result;
    }
}
