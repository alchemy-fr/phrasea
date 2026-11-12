<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Security\DataboxIntegrationClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The OAuth clients of the Databox integrations, whose access tokens Expose accepts.
 */
class DataboxIntegrationClientTest extends TestCase
{
    private const string DATABOX_API_URL = 'https://api-databox.phrasea.test';

    /**
     * A client of the integrations not created by Databox, as Keycloak represents it.
     */
    private static function client(array $overrides = []): array
    {
        return [
            'clientId' => 'my-integration',
            'enabled' => true,
            'publicClient' => true,
            'bearerOnly' => false,
            'standardFlowEnabled' => true,
            'implicitFlowEnabled' => false,
            'directAccessGrantsEnabled' => false,
            'rootUrl' => self::DATABOX_API_URL,
            'redirectUris' => [self::DATABOX_API_URL.'/integrations/*'],
            ...$overrides,
        ];
    }

    public function testAcceptsTheClientsCreatedByDatabox(): void
    {
        $client = self::client([
            'redirectUris' => ['https://elsewhere.test/*'],
            'directAccessGrantsEnabled' => true,
            'attributes' => [DataboxIntegrationClient::CREATED_BY_ATTRIBUTE => DataboxIntegrationClient::CREATED_BY],
        ]);

        $this->assertTrue(DataboxIntegrationClient::isAccepted($client, self::DATABOX_API_URL));
        $this->assertTrue(DataboxIntegrationClient::isAccepted($client, ''));
        $this->assertFalse(DataboxIntegrationClient::isAccepted([...$client, 'enabled' => false], self::DATABOX_API_URL));
    }

    /**
     * @return iterable<string, array{array}>
     */
    public static function provideAcceptedClients(): iterable
    {
        yield 'any integration' => [self::client()];
        yield 'one integration' => [self::client(['redirectUris' => [self::DATABOX_API_URL.'/integrations/11111111-1111-1111-1111-111111111111/code']])];
        yield 'several URIs' => [self::client(['redirectUris' => [
            self::DATABOX_API_URL.'/integrations/a/code',
            self::DATABOX_API_URL.'/integrations/b/code',
        ]])];
        yield 'relative URI' => [self::client(['redirectUris' => ['/integrations/*']])];
        yield 'relative URI, root URL ending with a slash' => [self::client(['rootUrl' => self::DATABOX_API_URL.'/', 'redirectUris' => ['/integrations/*']])];
    }

    #[DataProvider('provideAcceptedClients')]
    public function testAcceptsTheClientsWhoseRedirectUrisAllLeadToTheIntegrations(array $client): void
    {
        $this->assertTrue(DataboxIntegrationClient::isAccepted($client, self::DATABOX_API_URL));
        $this->assertTrue(DataboxIntegrationClient::isAccepted($client, self::DATABOX_API_URL.'/'));
    }

    /**
     * @return iterable<string, array{array, 1?: string}>
     */
    public static function provideRefusedClients(): iterable
    {
        yield 'one URI outside Databox' => [self::client(['redirectUris' => [
            self::DATABOX_API_URL.'/integrations/*',
            'https://elsewhere.test/*',
        ]])];
        yield 'any URI' => [self::client(['redirectUris' => ['*']])];
        yield 'the whole Databox API' => [self::client(['redirectUris' => [self::DATABOX_API_URL.'/*']])];
        yield 'another host sharing the prefix' => [self::client(['redirectUris' => ['https://api-databox.phrasea.test.evil/integrations/*']])];
        yield 'a path sharing the prefix' => [self::client(['redirectUris' => [self::DATABOX_API_URL.'/integrations*']])];
        yield 'Keycloak shortcuts' => [self::client(['redirectUris' => ['+']])];
        yield 'no URI' => [self::client(['redirectUris' => []])];
        yield 'relative URI without root URL' => [self::client(['rootUrl' => null, 'redirectUris' => ['/integrations/*']])];
        yield 'relative URI under another root URL' => [self::client(['rootUrl' => 'https://elsewhere.test', 'redirectUris' => ['/integrations/*']])];
        yield 'direct access grants' => [self::client(['directAccessGrantsEnabled' => true])];
        yield 'implicit flow' => [self::client(['implicitFlowEnabled' => true])];
        yield 'confidential client' => [self::client(['publicClient' => false])];
        yield 'bearer only' => [self::client(['bearerOnly' => true])];
        yield 'no standard flow' => [self::client(['standardFlowEnabled' => false])];
        yield 'disabled client' => [self::client(['enabled' => false])];
        yield 'another Databox' => [self::client(), 'https://api-databox.other.test'];
        yield 'empty DATABOX_API_URL' => [self::client(), ''];
    }

    #[DataProvider('provideRefusedClients')]
    public function testRefusesTheOtherClients(array $client, string $databoxApiUrl = self::DATABOX_API_URL): void
    {
        $this->assertFalse(DataboxIntegrationClient::isAccepted($client, $databoxApiUrl));
    }
}
