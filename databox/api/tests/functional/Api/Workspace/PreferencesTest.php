<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\UserPreference;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET/PUT /preferences (UpdateUserPreferenceAction): a per-user key/value store,
 * updated one key at a time.
 */
final class PreferencesTest extends AbstractDataboxTestCase
{
    use WorkspaceTestHelperTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private function put(?string $userId, string $body): array
    {
        $response = static::createClient()->request('PUT', '/preferences', [
            'headers' => self::authHeaders($userId) + ['Content-Type' => 'application/json'],
            'body' => $body,
        ]);

        return json_decode($response->getContent(false), true);
    }

    private function get(?string $userId): string
    {
        return static::createClient()->request('GET', '/preferences', [
            'headers' => self::authHeaders($userId),
        ])->getContent(false);
    }

    public function testPreferencesRequireAuthentication(): void
    {
        $this->get(null);
        $this->assertResponseStatusCodeSame(401);

        $this->put(null, json_encode(['name' => 'theme', 'value' => 'dark']));
        $this->assertResponseStatusCodeSame(401);

        $this->assertSame([], self::getEntityManager()->getRepository(UserPreference::class)->findAll());
    }

    public function testEmptyPreferencesAreAnEmptyObject(): void
    {
        $content = $this->get(self::USER);

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        // An object, not an empty array
        $this->assertSame('{}', $content);
        // Reading does not create the row
        $this->assertSame([], self::getEntityManager()->getRepository(UserPreference::class)->findAll());
    }

    public function testUpdateMergesKeysOneAtATime(): void
    {
        $data = $this->put(self::USER, json_encode(['name' => 'theme', 'value' => 'dark']));
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(['theme' => 'dark'], $data);

        $data = $this->put(self::USER, json_encode(['name' => 'layout', 'value' => ['grid' => true, 'size' => 3]]));
        $this->assertSame(['theme' => 'dark', 'layout' => ['grid' => true, 'size' => 3]], $data);

        // Overwrite a key
        $data = $this->put(self::USER, json_encode(['name' => 'theme', 'value' => 'light']));
        $this->assertSame(['theme' => 'light', 'layout' => ['grid' => true, 'size' => 3]], $data);

        // Missing value = null (the key is kept)
        $data = $this->put(self::USER, json_encode(['name' => 'theme']));
        $this->assertSame(['theme' => null, 'layout' => ['grid' => true, 'size' => 3]], $data);

        $this->assertSame(
            ['theme' => null, 'layout' => ['grid' => true, 'size' => 3]],
            json_decode($this->get(self::USER), true),
        );
    }

    public function testResetReplacesAllPreferences(): void
    {
        $this->put(self::USER, json_encode(['name' => 'theme', 'value' => 'dark']));
        $this->put(self::USER, json_encode(['name' => 'layout', 'value' => 'grid']));

        $data = $this->put(self::USER, json_encode(['name' => 'lang', 'value' => 'fr', 'reset' => 'true']));

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(['lang' => 'fr'], $data);
        $this->assertSame(['lang' => 'fr'], json_decode($this->get(self::USER), true));
    }

    public function testPreferencesArePerUser(): void
    {
        $this->put(self::USER, json_encode(['name' => 'theme', 'value' => 'dark']));
        $this->put(self::OTHER, json_encode(['name' => 'theme', 'value' => 'light']));

        $this->assertSame(['theme' => 'dark'], json_decode($this->get(self::USER), true));
        $this->assertSame(['theme' => 'light'], json_decode($this->get(self::OTHER), true));
        $this->assertCount(2, self::getEntityManager()->getRepository(UserPreference::class)->findAll());
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'missing name' => [json_encode(['value' => 'x'])],
            'empty name' => [json_encode(['name' => '', 'value' => 'x'])],
            'empty object' => ['{}'],
        ];
    }

    /**
     * @dataProvider invalidPayloadProvider
     */
    public function testInvalidPayload(string $body): void
    {
        $this->put(self::USER, $body);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame([], self::getEntityManager()->getRepository(UserPreference::class)->findAll());
    }

    public function testMalformedJsonIsABadRequest(): void
    {
        $this->markTestIncomplete('BUG: Alchemy\\CoreBundle\\Listener\\JsonConverterSubscriber throws its BadRequestHttpException on every kernel.controller event, including the error-rendering sub-request (same body): the exception escapes the kernel instead of producing a 400 response');

        $this->put(self::USER, '{not json');

        $this->assertResponseStatusCodeSame(400);
    }
}
