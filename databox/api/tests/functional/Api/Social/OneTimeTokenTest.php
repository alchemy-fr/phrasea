<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\OneTimeTokenAuthenticator;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * `POST /ott`: a short-lived one-time token standing for the current user,
 * used where a Bearer cannot be sent (e.g. the OAuth "state" of an
 * integration popup, consumed by IntegrationAuthController).
 */
final class OneTimeTokenTest extends AbstractDataboxTestCase
{
    use SocialTestTrait;

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/ott', ['json' => []]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testTokenIsConsumedOnlyOnce(): void
    {
        $client = static::createClient();
        // The token lives in a cache pool of the kernel
        $client->disableReboot();

        $data = $client->request('POST', '/ott', self::auth(KeycloakClientTestMock::USER_UID, ['json' => []]))->toArray();
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(128, strlen((string) $data['token']));

        $other = $client->request('POST', '/ott', self::auth(KeycloakClientTestMock::USER_UID, ['json' => []]))->toArray();
        $this->assertNotSame($data['token'], $other['token']);

        /** @var OneTimeTokenAuthenticator $authenticator */
        $authenticator = self::getService(OneTimeTokenAuthenticator::class);
        $user = $authenticator->consumeToken($data['token']);
        $this->assertInstanceOf(JwtUser::class, $user);
        $this->assertSame(KeycloakClientTestMock::USER_UID, $user->getUserIdentifier());
        $this->assertSame('user', $user->getUsername());

        $this->expectException(AuthenticationException::class);
        $authenticator->consumeToken($data['token']);
    }

    public function testUnknownTokenIsRejected(): void
    {
        static::createClient();

        $this->expectException(AuthenticationException::class);
        self::getService(OneTimeTokenAuthenticator::class)->consumeToken('forged');
    }
}
