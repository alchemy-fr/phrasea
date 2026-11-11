<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp;

use App\Mcp\ApiClient;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApiClientTest extends TestCase
{
    private RecordingHttpKernel $kernel;
    private RequestStack $requestStack;
    private ApiClient $client;

    protected function setUp(): void
    {
        $this->kernel = new RecordingHttpKernel();
        $this->requestStack = new RequestStack();
        $this->client = new ApiClient($this->kernel, $this->requestStack);
    }

    public function testGetSendsJsonSubRequest(): void
    {
        $this->kernel->willRespond(['id' => '42', 'title' => 'Foo']);

        $this->assertSame(['id' => '42', 'title' => 'Foo'], $this->client->get('/publications/42', ['a' => 'b']));

        $this->assertCount(1, $this->kernel->calls);
        $this->assertSame(HttpKernelInterface::SUB_REQUEST, $this->kernel->calls[0]['type']);
        $request = $this->kernel->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/publications/42', $request->getPathInfo());
        $this->assertSame(['a' => 'b'], $request->query->all());
        $this->assertSame('application/json', $request->headers->get('Accept'));
        $this->assertSame('', $request->getContent());
    }

    public function testSubRequestInheritsHostSchemeAndAuthorizationOfMainRequest(): void
    {
        $this->requestStack->push(Request::create('https://api-expose.example.com/mcp', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer secret-token',
        ]));
        $this->kernel->willRespond([]);

        $this->client->get('/publications');

        $request = $this->kernel->lastRequest();
        $this->assertSame('api-expose.example.com', $request->getHost());
        $this->assertTrue($request->isSecure());
        $this->assertSame('Bearer secret-token', $request->headers->get('Authorization'));
    }

    public function testSubRequestWithoutMainRequest(): void
    {
        $this->kernel->willRespond([]);

        $this->client->get('/publications');

        $this->assertFalse($this->kernel->lastRequest()->headers->has('Authorization'));
    }

    public function testGetReturnsScalarResponses(): void
    {
        $this->kernel->willRespond(true);

        $this->assertTrue($this->client->get('/publications/slug-availability/foo'));
    }

    public function testPostAndPatchSendJsonBody(): void
    {
        $this->kernel
            ->willRespond(['id' => '1'], 201)
            ->willRespond(['id' => '1']);

        $this->assertSame(['id' => '1'], $this->client->post('/publications', ['title' => 'Foo']));
        $request = $this->kernel->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('application/json', $request->headers->get('Content-Type'));
        $this->assertSame(['title' => 'Foo'], $this->kernel->lastBody());

        $this->client->patch('/publications/1', ['title' => 'Bar']);
        $this->assertSame('PATCH', $this->kernel->lastRequest()->getMethod());
        $this->assertSame('application/merge-patch+json', $this->kernel->lastRequest()->headers->get('Content-Type'));
        $this->assertSame(['title' => 'Bar'], $this->kernel->lastBody());
    }

    public function testDeleteAcceptsEmptyResponse(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->client->delete('/publications/1');

        $this->assertSame('DELETE', $this->kernel->lastRequest()->getMethod());
    }

    public function testGetCollectionUnwrapsHydraCollection(): void
    {
        $this->kernel->willRespond([
            '@context' => '/contexts/Publication',
            '@id' => '/publications',
            '@type' => 'Collection',
            'member' => [
                ['@id' => '/publications/1', '@type' => 'Publication', 'id' => '1', 'cover' => ['@id' => '/assets/9', 'id' => '9']],
                ['@id' => '/publications/2', '@type' => 'Publication', 'id' => '2'],
            ],
            'totalItems' => 32,
            'view' => [
                '@id' => '/publications?page=2',
                'next' => '/publications?page=3',
            ],
        ]);

        $result = $this->client->getCollection('/publications', ['page' => 2]);

        $this->assertSame('application/ld+json', $this->kernel->lastRequest()->headers->get('Accept'));
        $this->assertSame([
            'items' => [
                ['id' => '1', 'cover' => ['id' => '9']],
                ['id' => '2'],
            ],
            'totalItems' => 32,
            'page' => 2,
            'hasNextPage' => true,
        ], $result);
    }

    public function testGetCollectionLastPage(): void
    {
        $this->kernel->willRespond([
            'member' => [],
            'totalItems' => 0,
        ]);

        $result = $this->client->getCollection('/publications');

        $this->assertSame(['items' => [], 'totalItems' => 0, 'page' => 1, 'hasNextPage' => false], $result);
    }

    public function testNestedQueryParameters(): void
    {
        $this->kernel->willRespond(['member' => []]);

        $this->client->getCollection('/publications', ['order' => ['title' => 'asc'], 'flatten' => 'true']);

        $this->assertSame(['order' => ['title' => 'asc'], 'flatten' => 'true'], $this->kernel->lastRequest()->query->all());
    }

    #[DataProvider('getErrorResponses')]
    public function testErrorResponseThrowsToolCallException(mixed $data, int $status, string $expectedMessage): void
    {
        $this->kernel->willRespond($data, $status);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->client->post('/publications', ['title' => 'Foo']);
    }

    public static function getErrorResponses(): array
    {
        return [
            'violations' => [
                [
                    'detail' => 'title: This value is too long.',
                    'violations' => [
                        ['propertyPath' => 'title', 'message' => 'This value is too long.'],
                        ['propertyPath' => '', 'message' => 'Publication has circular parenthood'],
                    ],
                ],
                422,
                'Expose API responded 422: title: This value is too long.; Publication has circular parenthood',
            ],
            'problem detail' => [['title' => 'An error occurred', 'detail' => 'Access Denied.'], 403, 'Expose API responded 403: Access Denied.'],
            'description' => [['description' => 'Not Found'], 404, 'Expose API responded 404: Not Found'],
            'message' => [['message' => 'Invalid token.'], 401, 'Expose API responded 401: Invalid token.'],
            'title only' => [['title' => 'Conflict'], 409, 'Expose API responded 409: Conflict'],
            'no body' => [null, 500, 'Expose API responded 500: 500'],
        ];
    }
}
