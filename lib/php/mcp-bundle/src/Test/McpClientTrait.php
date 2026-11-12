<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Test;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON-RPC client of the MCP endpoint for functional tests: keeps the MCP session opened by
 * mcpInitialize() for the following calls of the test.
 */
trait McpClientTrait
{
    private const string MCP_PROTOCOL_VERSION = '2025-06-18';

    private ?string $mcpSessionId = null;

    /**
     * Sends a request to the application with the test client of the application.
     *
     * @param array<string, string> $server
     */
    abstract protected function mcpRequest(?string $accessToken, string $method, string $uri, array $server = [], ?string $content = null): Response;

    protected function getMcpPath(): string
    {
        return '/mcp';
    }

    protected function mcpInitialize(string $accessToken): void
    {
        $this->mcpRpc($accessToken, 'initialize', [
            'protocolVersion' => self::MCP_PROTOCOL_VERSION,
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ]);
        $this->mcpRpc($accessToken, 'notifications/initialized', notification: true);
    }

    /**
     * @return mixed the structured content of the tool result, or the error message when $expectError is true
     */
    protected function mcpCallTool(string $accessToken, string $name, array $arguments = [], bool $expectError = false): mixed
    {
        $result = $this->mcpRpc($accessToken, 'tools/call', [
            'name' => $name,
            'arguments' => (object) $arguments,
        ]);

        Assert::assertSame($expectError, $result['isError'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));
        if ($expectError) {
            return $result['content'][0]['text'];
        }

        return $result['structuredContent'];
    }

    protected function mcpRpc(string $accessToken, string $method, ?array $params = null, bool $notification = false): ?array
    {
        $body = ['jsonrpc' => '2.0', 'method' => $method];
        if (!$notification) {
            $body['id'] = uniqid();
        }
        if (null !== $params) {
            $body['params'] = $params;
        }

        $server = [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => self::MCP_PROTOCOL_VERSION,
        ];
        if (null !== $this->mcpSessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $this->mcpSessionId;
        }

        $response = $this->mcpRequest($accessToken, 'POST', $this->getMcpPath(), $server, json_encode($body, JSON_THROW_ON_ERROR));
        Assert::assertTrue($response->isSuccessful(), (string) $response->getContent());
        $this->mcpSessionId ??= $response->headers->get('Mcp-Session-Id');

        if ($notification) {
            return null;
        }

        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertArrayHasKey('result', $json, (string) $response->getContent());

        return $json['result'];
    }
}
