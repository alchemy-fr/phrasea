<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\AuthBundle\Security\AllowedClientIdsProviderInterface;
use Alchemy\AuthBundle\Security\JwtValidator;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

class JwtValidatorTest extends TestCase
{
    private const string KEYCLOAK_URL = 'https://keycloak.test';
    private const string ISSUER = self::KEYCLOAK_URL.'/realms/phrasea';
    private const string NOW = '2026-11-11 12:00:00';
    private const string ADMIN_CLIENT_ID = 'databox-admin';

    public function testAcceptsAnAccessTokenOfAnAllowedClient(): void
    {
        $this->assertTrue($this->createValidator()->isTokenValid($this->createToken()));
    }

    public function testRefusesAnIdToken(): void
    {
        $this->assertFalse($this->createValidator()->isTokenValid($this->createToken(['typ' => 'ID'])));
    }

    public function testRefusesATokenWithoutType(): void
    {
        $this->assertFalse($this->createValidator()->isTokenValid($this->createToken(['typ' => null])));
    }

    public function testRefusesAnUnknownClient(): void
    {
        $this->assertFalse($this->createValidator()->isTokenValid($this->createToken(['azp' => 'another-app'])));
    }

    public function testRefusesATokenWithoutClient(): void
    {
        $this->assertFalse($this->createValidator()->isTokenValid($this->createToken(['azp' => null])));
    }

    public function testAcceptsTheClientOfTheApplication(): void
    {
        $this->assertTrue($this->createValidator()->isTokenValid($this->createToken(['azp' => self::ADMIN_CLIENT_ID])));
    }

    public function testFallsBackToTheClientIdClaim(): void
    {
        $validator = $this->createValidator();

        $this->assertTrue($validator->isTokenValid($this->createToken(['azp' => null, 'client_id' => 'databox-indexer'])));
        $this->assertFalse($validator->isTokenValid($this->createToken(['azp' => null, 'client_id' => 'another-app'])));
        // azp wins over client_id
        $this->assertFalse($validator->isTokenValid($this->createToken(['azp' => 'another-app', 'client_id' => 'databox-indexer'])));
    }

    public function testAsksTheProvidersForTheOtherClients(): void
    {
        $asked = [];
        $provider = $this->createMock(AllowedClientIdsProviderInterface::class);
        $provider->method('isClientAllowed')->willReturnCallback(function (string $clientId) use (&$asked): bool {
            $asked[] = $clientId;

            return 'an-integration' === $clientId;
        });
        $validator = $this->createValidator(providers: [$provider]);

        $this->assertTrue($validator->isTokenValid($this->createToken(['azp' => 'an-integration'])));
        $this->assertFalse($validator->isTokenValid($this->createToken(['azp' => 'another-app'])));
        $this->assertTrue($validator->isTokenValid($this->createToken()));
        $this->assertSame(['an-integration', 'another-app'], $asked);
    }

    public function testNeverAsksTheProvidersForAnInvalidToken(): void
    {
        $provider = $this->createMock(AllowedClientIdsProviderInterface::class);
        $provider->expects($this->never())->method('isClientAllowed');
        $validator = $this->createValidator(providers: [$provider]);

        $this->assertFalse($validator->isTokenValid($this->createToken(['azp' => 'an-integration', 'typ' => 'ID'])));
        $this->assertFalse($validator->isTokenValid($this->createToken(['azp' => 'an-integration'], issuer: 'https://other.test/realms/phrasea')));
    }

    public function testRefusesATokenSignedByAnotherKey(): void
    {
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKey, $pem);

        $this->assertFalse($this->createValidator()->isTokenValid($this->createToken(signingKey: InMemory::plainText($pem))));
    }

    public function testRefusesAnotherIssuer(): void
    {
        $this->assertFalse($this->createValidator()->isTokenValid($this->createToken(issuer: 'https://other.test/realms/phrasea')));
    }

    #[DataProvider('getDates')]
    public function testChecksTheDates(bool $expected, string $issuedAt, string $notBefore, string $expiresAt): void
    {
        $token = $this->createToken(dates: [$issuedAt, $notBefore, $expiresAt]);

        $this->assertSame($expected, $this->createValidator()->isTokenValid($token));
    }

    public static function getDates(): array
    {
        return [
            'valid' => [true, '-1 minute', '-1 minute', '+5 minutes'],
            'expired' => [false, '-10 minutes', '-10 minutes', '-1 minute'],
            'expired within the leeway' => [true, '-10 minutes', '-10 minutes', '-20 seconds'],
            'not before in the future' => [false, '-1 minute', '+1 minute', '+5 minutes'],
            'not before within the leeway' => [true, '-1 minute', '+20 seconds', '+5 minutes'],
            'issued in the future' => [false, '+1 minute', '-1 minute', '+5 minutes'],
            'issued in the future within the leeway' => [true, '+20 seconds', '-1 minute', '+5 minutes'],
        ];
    }

    public function testTheLeewayIsConfigurable(): void
    {
        $token = $this->createToken(dates: ['-10 minutes', '-10 minutes', '-20 seconds']);

        $this->assertFalse($this->createValidator(leeway: 0)->isTokenValid($token));
        $this->assertTrue($this->createValidator(leeway: 60)->isTokenValid($this->createToken(dates: ['+50 seconds', '-1 minute', '+5 minutes'])));
    }

    /**
     * @param AllowedClientIdsProviderInterface[] $providers
     */
    private function createValidator(int $leeway = 30, array $providers = []): JwtValidator
    {
        return new JwtValidator(
            new KeycloakClient(
                new KeycloakClientTestMock(),
                new KeycloakUrlGenerator(self::KEYCLOAK_URL, null, 'phrasea'),
                new ArrayAdapter(),
                self::ADMIN_CLIENT_ID,
                'secret',
            ),
            new KeycloakUrlGenerator(self::KEYCLOAK_URL, null, 'phrasea'),
            new MockClock(self::NOW),
            new NullLogger(),
            ['databox-app', ' databox-indexer ', ''],
            self::ADMIN_CLIENT_ID,
            $leeway,
            $providers,
        );
    }

    /**
     * @param array<string, mixed>          $claims the claims replacing the ones of an access token of "databox-app" (null removes the claim)
     * @param array{string, string, string} $dates  iat, nbf and exp, relative to NOW
     */
    private function createToken(
        array $claims = [],
        string $issuer = self::ISSUER,
        array $dates = ['-1 minute', '-1 minute', '+5 minutes'],
        ?InMemory $signingKey = null,
    ): UnencryptedToken {
        $now = new \DateTimeImmutable(self::NOW);
        $builder = new Builder(new JoseEncoder(), ChainedFormatter::default())
            ->issuedBy($issuer)
            ->issuedAt($now->modify($dates[0]))
            ->canOnlyBeUsedAfter($now->modify($dates[1]))
            ->expiresAt($now->modify($dates[2]))
            ->relatedTo(KeycloakClientTestMock::USER_UID);

        $claims = [
            'typ' => JwtValidator::ACCESS_TOKEN_TYPE,
            'azp' => 'databox-app',
            'preferred_username' => 'user',
            ...$claims,
        ];
        foreach ($claims as $name => $value) {
            if (null !== $value) {
                $builder = $builder->withClaim($name, $value);
            }
        }

        $keyDir = dirname((string) new \ReflectionClass(KeycloakClientTestMock::class)->getFileName());
        $jwt = $builder->getToken(new Sha256(), $signingKey ?? InMemory::file($keyDir.'/key.pem'))->toString();

        $token = new Parser(new JoseEncoder())->parse($jwt);
        \assert($token instanceof UnencryptedToken);

        return $token;
    }
}
