<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Message\DeployConfig;
use Alchemy\MessengerBundle\Transport\TestTransport;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Config\Schema\DataboxConfigSchema;
use App\Tests\AbstractDataboxTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The organisation theme (/client-theme): readable by everyone, including
 * anonymous visitors, and only writable by administrators with a strictly
 * validated payload since its values end up as CSS in every user's browser.
 * It lives in the `databox.theme` configurator entry, and every write
 * schedules the push of the stack configuration to the bucket.
 */
class ClientThemeTest extends AbstractDataboxTestCase
{
    private const string ENDPOINT = '/client-theme';

    private const array VALID_THEME = [
        'name' => 'Acme',
        'default' => true,
        'colors' => [
            'primary' => '#FF6600',
            'primary-foreground' => '#ffffff',
            'background' => '',
        ],
        'dark' => [
            'primary' => '#FF8A3D',
        ],
        'radius' => 0.75,
        'fontSize' => 15,
        'fontFamily' => 'Inter, "Helvetica Neue", sans-serif',
        'letterSpacing' => 0.01,
    ];

    private Client $client;
    private InMemoryTransport $inMemory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        // The kernel must survive the requests, otherwise the intercepting
        // transport is rebuilt and forgets what was sent
        $this->client->getKernelBrowser()->disableReboot();

        // The stack configuration push is handled synchronously by the test
        // transport: intercept it, it would push to the bucket of the stack.
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $this->inMemory = $transport->intercept();

        // The configurator entries live in their own (test: SQLite) database,
        // outside of the fixtures: (re)create its schema for each test.
        $em = $this->getConfiguratorEntityManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testAnonymousGetsNullWhenNoThemeIsDefined(): void
    {
        $client = $this->client;

        $response = $client->request('GET', self::ENDPOINT);

        $this->assertResponseStatusCodeSame(200);
        self::assertSame('null', $response->getContent());
    }

    public function testAdminCanDefineTheThemeAndEveryoneCanReadIt(): void
    {
        $client = $this->client;

        $response = $client->request('PUT', self::ENDPOINT, [
            'headers' => $this->adminHeaders(),
            'json' => self::VALID_THEME,
        ]);

        $this->assertResponseStatusCodeSame(200);
        $expected = [
            'name' => 'Acme',
            'default' => true,
            'colors' => [
                'primary' => '#ff6600',
                'primary-foreground' => '#ffffff',
            ],
            'dark' => [
                'primary' => '#ff8a3d',
            ],
            'radius' => 0.75,
            'fontSize' => 15,
            'fontFamily' => 'Inter, "Helvetica Neue", sans-serif',
            'letterSpacing' => 0.01,
        ];
        self::assertSame($expected, json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        self::assertCount(1, $this->getDeployMessages(), 'The stack configuration push must be scheduled');

        // Stored as a regular configurator entry, so that it is dumped with the rest of the stack configuration
        $entry = $this->getConfiguratorEntityManager()->getRepository(ConfiguratorEntry::class)->findOneBy(['name' => DataboxConfigSchema::THEME_KEY]);
        self::assertNotNull($entry);
        self::assertSame($expected, json_decode($entry->getValue(), true, flags: JSON_THROW_ON_ERROR));

        $response = $client->request('GET', self::ENDPOINT);
        $this->assertResponseStatusCodeSame(200);
        self::assertSame($expected, json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));

        // Updating replaces the whole theme, in the same entry
        $client->request('PUT', self::ENDPOINT, [
            'headers' => $this->adminHeaders(),
            'json' => ['name' => 'Acme light'],
        ]);
        $this->assertResponseStatusCodeSame(200);
        self::assertCount(1, $this->getConfiguratorEntityManager()->getRepository(ConfiguratorEntry::class)->findAll());

        $response = $client->request('GET', self::ENDPOINT, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID)],
        ]);
        self::assertSame([
            'name' => 'Acme light',
            'default' => false,
            'colors' => [],
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testAdminCanRemoveTheTheme(): void
    {
        $client = $this->client;

        $client->request('PUT', self::ENDPOINT, [
            'headers' => $this->adminHeaders(),
            'json' => self::VALID_THEME,
        ]);
        $this->assertResponseStatusCodeSame(200);

        $client->request('DELETE', self::ENDPOINT, [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertResponseStatusCodeSame(204);
        // The in-memory transport is reset between requests: this is the DELETE's push
        self::assertCount(1, $this->getDeployMessages());

        $response = $client->request('GET', self::ENDPOINT);
        self::assertSame('null', $response->getContent());
        self::assertCount(0, $this->getConfiguratorEntityManager()->getRepository(ConfiguratorEntry::class)->findAll());

        // Removing an absent theme is idempotent and schedules nothing
        $client->request('DELETE', self::ENDPOINT, [
            'headers' => $this->adminHeaders(),
        ]);
        $this->assertResponseStatusCodeSame(204);
        self::assertCount(0, $this->getDeployMessages());
    }

    public function testIgnoresAnInvalidEntryEditedByHand(): void
    {
        $em = $this->getConfiguratorEntityManager();
        $entry = new ConfiguratorEntry();
        $entry->setName(DataboxConfigSchema::THEME_KEY);
        $entry->setValue('{"name": "Broken", "colors": {"primary": "red"}}');
        $em->persist($entry);
        $em->flush();

        $client = $this->client;
        $response = $client->request('GET', self::ENDPOINT);
        $this->assertResponseStatusCodeSame(200);
        self::assertSame('null', $response->getContent());
    }

    /**
     * @dataProvider invalidThemeProvider
     */
    public function testRejectsInvalidThemes(array $theme, string $propertyPath): void
    {
        $client = $this->client;

        $response = $client->request('PUT', self::ENDPOINT, [
            'headers' => $this->adminHeaders(),
            'json' => $theme,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($response->getContent(false), true, flags: JSON_THROW_ON_ERROR);
        $paths = array_column($data['violations'], 'propertyPath');
        self::assertContains($propertyPath, $paths, sprintf('Expected a violation on "%s", got: %s', $propertyPath, implode(', ', $paths)));
        self::assertCount(0, $this->getDeployMessages());

        $response = $client->request('GET', self::ENDPOINT);
        self::assertSame('null', $response->getContent(), 'An invalid theme must not be stored');
    }

    public static function invalidThemeProvider(): iterable
    {
        yield 'missing name' => [['mode' => 'light'], 'name'];
        yield 'blank name' => [['name' => '   '], 'name'];
        yield 'name too long' => [['name' => str_repeat('a', 51)], 'name'];
        yield 'unknown property' => [['name' => 'x', 'mode' => 'dark'], 'mode'];
        yield 'dark palette not an object' => [['name' => 'x', 'dark' => ['#000000']], 'dark'];
        yield 'dark palette with a bad color' => [['name' => 'x', 'dark' => ['primary' => 'black']], 'dark.primary'];
        yield 'letter spacing out of range' => [['name' => 'x', 'letterSpacing' => 1], 'letterSpacing'];
        yield 'unknown css property' => [['name' => 'x', 'css' => 'body{}'], 'css'];
        yield 'unknown color token' => [['name' => 'x', 'colors' => ['evil' => '#000000']], 'colors.evil'];
        yield 'color is not hex' => [['name' => 'x', 'colors' => ['primary' => 'red']], 'colors.primary'];
        yield 'color with css injection' => [['name' => 'x', 'colors' => ['primary' => '#000; background: url(x)']], 'colors.primary'];
        yield 'radius out of range' => [['name' => 'x', 'radius' => 5], 'radius'];
        yield 'radius not numeric' => [['name' => 'x', 'radius' => '1rem'], 'radius'];
        yield 'font size out of range' => [['name' => 'x', 'fontSize' => 40], 'fontSize'];
        yield 'font family with unsafe chars' => [['name' => 'x', 'fontFamily' => 'Inter; color: red'], 'fontFamily'];
        yield 'default not boolean' => [['name' => 'x', 'default' => 'yes'], 'default'];
    }

    public function testTheConfiguratorEntryIsValidatedWithTheSameRules(): void
    {
        $validator = static::getContainer()->get('validator');

        $entry = new ConfiguratorEntry();
        $entry->setName(DataboxConfigSchema::THEME_KEY);
        $entry->setValue(json_encode(self::VALID_THEME, JSON_THROW_ON_ERROR));
        self::assertCount(0, $validator->validate($entry));

        $entry->setValue('{"name": "x", "colors": {"primary": "red"}}');
        $violations = $validator->validate($entry);
        self::assertCount(1, $violations);
        self::assertSame('value', $violations[0]->getPropertyPath());
        self::assertStringContainsString('colors.primary', (string) $violations[0]->getMessage());

        $entry->setValue('{not json');
        self::assertCount(1, $validator->validate($entry));
    }

    public function testRegularUserCannotWriteTheTheme(): void
    {
        $client = $this->client;

        $client->request('PUT', self::ENDPOINT, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID)],
            'json' => self::VALID_THEME,
        ]);
        $this->assertResponseStatusCodeSame(403);

        $client->request('DELETE', self::ENDPOINT, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID)],
        ]);
        $this->assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->getDeployMessages());
    }

    public function testAnonymousCannotWriteTheTheme(): void
    {
        $client = $this->client;

        $client->request('PUT', self::ENDPOINT, [
            'json' => self::VALID_THEME,
        ]);
        $this->assertResponseStatusCodeSame(401);

        $client->request('DELETE', self::ENDPOINT);
        $this->assertResponseStatusCodeSame(401);
    }

    private function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
        ];
    }

    private function getConfiguratorEntityManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine')->getManagerForClass(ConfiguratorEntry::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    /**
     * @return Envelope[]
     */
    private function getDeployMessages(): array
    {
        return array_values(array_filter(
            $this->inMemory->getSent(),
            fn (Envelope $envelope): bool => $envelope->getMessage() instanceof DeployConfig
        ));
    }
}
