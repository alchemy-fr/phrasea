<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Test;

use Alchemy\McpBundle\Api\ApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit test of MCP tools: checks the sub-requests they send to the REST API.
 */
abstract class AbstractToolTestCase extends TestCase
{
    protected RecordingHttpKernel $kernel;
    protected ApiClient $client;

    protected function setUp(): void
    {
        $this->kernel = new RecordingHttpKernel();
        $this->client = new ApiClient($this->kernel, new RequestStack());
    }

    protected function assertLastRequest(string $method, string $path, ?array $query = null, ?array $body = null): void
    {
        $request = $this->kernel->lastRequest();
        $this->assertSame($method, $request->getMethod());
        $this->assertSame($path, $request->getPathInfo());
        if (null !== $query) {
            $this->assertSame($query, $request->query->all());
        }
        $this->assertSame($body, $this->kernel->lastBody());
    }

    protected static function emptyCollection(): array
    {
        return ['member' => [], 'totalItems' => 0];
    }
}
