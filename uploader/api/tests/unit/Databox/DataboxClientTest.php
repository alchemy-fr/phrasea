<?php

declare(strict_types=1);

namespace App\Tests\Unit\Databox;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Consumer\TargetPrivateOrigins;
use App\Databox\DataboxClient;
use App\Databox\DataboxException;
use App\Entity\Target;
use App\Security\UploadLinkUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class DataboxClientTest extends TestCase
{
    private const string DATABOX_API_URL = 'https://api-databox.phrasea.local';
    private const string INTEGRATION_ID = '5b1c2d3e-4f50-4617-8293-a4b5c6d7e8f9';
    private const string INTEGRATION_URL = self::DATABOX_API_URL.'/integrations/uploader/'.self::INTEGRATION_ID;
    private const string TARGET_ID = 'c0ffee00-0000-4000-8000-000000000001';
    private const string KEY = 'integration-key';

    /**
     * @var list<array{method: string, url: string, headers: array<string, string>}>
     */
    private array $requests = [];

    public function testAUserCallsTheDataboxApiWithTheirToken(): void
    {
        $client = $this->createClient(new JwtUser('the-jwt', 'user-id', 'user'));

        $this->assertSame(['member' => []], $client->get('/attribute-entities', ['list' => 'l1']));

        $this->assertCount(1, $this->requests);
        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame(self::DATABOX_API_URL.'/attribute-entities?list=l1', $this->requests[0]['url']);
        $this->assertSame('Bearer the-jwt', $this->requests[0]['headers']['authorization']);
        $this->assertSame('application/ld+json', $this->requests[0]['headers']['accept']);
        $this->assertSame('fr', $this->requests[0]['headers']['accept-language']);
    }

    public function testAnUploadLinkCallsDataboxAsTheIntegrationOfItsTargetWithItsKey(): void
    {
        $client = $this->createClient(self::createLinkUser(), self::createTarget());

        $this->assertSame(['member' => []], $client->get('/attribute-entities', ['list' => 'l1', 'query' => 'al']));

        $this->assertCount(1, $this->requests);
        $this->assertSame(self::DATABOX_API_URL.'/attribute-entities?list=l1&query=al', $this->requests[0]['url']);
        $this->assertSame('ApiKey '.self::INTEGRATION_ID.':'.self::KEY, $this->requests[0]['headers']['authorization']);
        $this->assertSame('application/ld+json', $this->requests[0]['headers']['accept']);
        $this->assertSame('fr', $this->requests[0]['headers']['accept-language']);
    }

    public function testAnExplicitApiKeySchemeIsAccepted(): void
    {
        $target = self::createTarget();
        $target->setAuthorizationScheme('ApiKey');
        $client = $this->createClient(self::createLinkUser(), $target);

        $client->get('/attribute-entities', ['list' => 'l1']);

        $this->assertSame('ApiKey '.self::INTEGRATION_ID.':'.self::KEY, $this->requests[0]['headers']['authorization']);
    }

    /**
     * Databox authenticates an integration by the ApiKey scheme only: another one would read the values anonymously.
     */
    public function testAnotherAuthorizationSchemeIsRefused(): void
    {
        $target = self::createTarget();
        $target->setAuthorizationScheme('Token');
        $client = $this->createClient(self::createLinkUser(), $target);

        $this->assertDataboxException(403, static fn () => $client->get('/attribute-entities', ['list' => 'l1']));
        $this->assertSame([], $this->requests);
    }

    public function testAnUploadLinkOnlyReadsTheEntities(): void
    {
        $client = $this->createClient(self::createLinkUser(), self::createTarget());

        $this->assertDataboxException(403, static fn () => $client->get('/workspaces'));
        $this->assertSame([], $this->requests);
    }

    #[DataProvider('provideUnboundTargets')]
    public function testTheTargetOfTheLinkMustBeBoundToAnUploaderIntegration(?string $targetUrl, ?string $key, bool $found = true): void
    {
        $target = self::createTarget($targetUrl, $key);
        $client = $this->createClient(self::createLinkUser(), $found ? $target : null);

        $this->assertDataboxException(403, static fn () => $client->get('/attribute-entities', ['list' => 'l1']));
        $this->assertSame([], $this->requests);
    }

    public static function provideUnboundTargets(): iterable
    {
        yield 'no target URL' => [null, self::KEY];
        yield 'not an integration URL' => ['https://consumer.example.com/hook', self::KEY];
        yield 'another integration' => [self::DATABOX_API_URL.'/integrations/other/'.self::INTEGRATION_ID.'/incoming-commit', self::KEY];
        yield 'not an integration ID' => [self::DATABOX_API_URL.'/integrations/uploader/x%0D%0Ay/incoming-commit', self::KEY];
        yield 'with a query' => [self::INTEGRATION_URL.'/incoming-commit?foo=bar', self::KEY];
        yield 'no key' => [self::INTEGRATION_URL.'/incoming-commit', null];
        yield 'empty key' => [self::INTEGRATION_URL.'/incoming-commit', ''];
        yield 'key "avoid"' => [self::INTEGRATION_URL.'/incoming-commit', 'avoid'];
        yield 'target not found' => [self::INTEGRATION_URL.'/incoming-commit', self::KEY, false];
    }

    public function testTheErrorsOfDataboxKeepTheirStatus(): void
    {
        $client = $this->createClient(self::createLinkUser(), self::createTarget(), new MockResponse('{}', ['http_code' => 404]));

        $this->assertDataboxException(404, static fn () => $client->get('/attribute-entities', ['list' => 'l1']));
    }

    public function testAPrivateHostOtherThanTheDataboxApiIsNotReached(): void
    {
        $target = self::createTarget('http://10.0.0.1/integrations/uploader/'.self::INTEGRATION_ID.'/incoming-commit');
        $client = $this->createClient(self::createLinkUser(), $target);

        $this->assertDataboxException(502, static fn () => $client->get('/attribute-entities', ['list' => 'l1']));
        $this->assertSame([], $this->requests);
    }

    public function testNobodyCannotCallDatabox(): void
    {
        $client = $this->createClient(null);

        $this->assertDataboxException(401, static fn () => $client->get('/attribute-entities'));
    }

    private function createClient(?UserInterface $user, ?Target $target = null, ?MockResponse $response = null): DataboxClient
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $header) {
                [$name, $value] = explode(': ', $header, 2);
                $headers[strtolower($name)] = $value;
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers];

            return $response ?? new MockResponse('{"member": []}', ['response_headers' => ['Content-Type' => 'application/json']]);
        });

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(
            static fn (string $class, mixed $id): ?Target => Target::class === $class && self::TARGET_ID === $id ? $target : null,
        );

        $request = new Request();
        $request->setLocale('fr');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new DataboxClient(
            $httpClient,
            $security,
            $requestStack,
            $em,
            new TargetPrivateOrigins(self::DATABOX_API_URL, null),
            self::DATABOX_API_URL,
        );
    }

    private static function createLinkUser(): UploadLinkUser
    {
        return new UploadLinkUser('link-id', self::TARGET_ID, 'owner-id');
    }

    private static function createTarget(?string $targetUrl = self::INTEGRATION_URL.'/incoming-commit', ?string $key = self::KEY): Target
    {
        $target = new Target();
        $target->setTargetUrl($targetUrl);
        $target->setAuthorizationKey($key);

        return $target;
    }

    private function assertDataboxException(int $status, callable $call): void
    {
        try {
            $call();
        } catch (DataboxException $e) {
            $this->assertSame($status, $e->statusCode, $e->getMessage());

            return;
        }

        $this->fail('A DataboxException was expected');
    }
}
