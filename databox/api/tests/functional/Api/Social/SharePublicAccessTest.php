<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Anonymous access through a share link: `GET /shares/{id}/public`,
 * `GET /s/{id}/a/{asset}/r/{renditionDefinition}` and `GET /s/{id}/a/{attachment}`.
 *
 * The token (query param, no Bearer needed) grants READ only while the share
 * is enabled and inside its [startsAt, expiresAt] window. The download routes
 * answer a bare 404 rather than 401/403 so a link never leaks what exists.
 */
final class SharePublicAccessTest extends AbstractDataboxTestCase
{
    use SocialTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string PREVIEW_URL = 'https://cdn.phrasea.test/preview.jpg';
    private const string ORIGINAL_URL = 'https://cdn.phrasea.test/original.tif';
    private const string ATTACHMENT_ORIGINAL_URL = 'https://cdn.phrasea.test/contract.pdf';
    private const string ATTACHMENT_PREVIEW_URL = 'https://cdn.phrasea.test/contract-preview.jpg';

    private Workspace $workspace;
    private Asset $asset;

    private function setUpWorkspace(): void
    {
        $this->workspace = $this->createNamedWorkspace(self::USER, 'public-share-ws');
        $this->asset = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
        ]);
    }

    private static function publicUri(Share $share, ?string $token): string
    {
        return sprintf('/shares/%s/public', $share->getId()).(null !== $token ? '?token='.$token : '');
    }

    public function testPublicAccessWithTokenAndNoBearer(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createShare(self::USER, [$this->asset], [
            'name' => 'internal name',
            'startsAt' => new \DateTimeImmutable('-1 day'),
            'expiresAt' => new \DateTimeImmutable('+1 day'),
        ]);

        $response = $client->request('GET', self::publicUri($share, $share->getToken()));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(1, $data['assets']);
        $this->assertSame($this->asset->getId(), $data['assets'][0]['id']);
        $this->assertArrayHasKey('alternateUrls', $data);
        $this->assertArrayHasKey('attachments', $data);
        // The public group does not expose the share management fields
        foreach (['token', 'name', 'enabled', 'startsAt', 'expiresAt'] as $key) {
            $this->assertArrayNotHasKey($key, $data, sprintf('"%s" must not be public', $key));
        }
    }

    public function testPublicAccessWithoutTokenIsDenied(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', self::publicUri($share, null));
        $this->assertResponseStatusCodeSame(401);

        $client->request('GET', self::publicUri($share, ''));
        $this->assertResponseStatusCodeSame(401);

        // A token of another share does not open this one
        $other = $this->createShare(self::USER, [$this->asset]);
        $client->request('GET', self::publicUri($share, $other->getToken()));
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedStrangerUsesTheTokenToo(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createShare(self::USER, [$this->asset]);

        // OTHER has no right on the workspace: the token alone opens the share
        $client->request('GET', self::publicUri($share, $share->getToken()), self::auth(self::OTHER));
        $this->assertResponseIsSuccessful();

        $client->request('GET', self::publicUri($share, 'wrong'), self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unavailableShareProvider(): iterable
    {
        yield 'disabled' => [['enabled' => false]];
        yield 'expired' => [['expiresAt' => '-1 minute']];
        yield 'not started yet' => [['startsAt' => '+1 hour']];
    }

    #[DataProvider('unavailableShareProvider')]
    public function testUnavailableShareRejectsItsToken(array $options): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        foreach (['startsAt', 'expiresAt'] as $date) {
            if (isset($options[$date])) {
                $options[$date] = new \DateTimeImmutable($options[$date]);
            }
        }
        $share = $this->createShare(self::USER, [$this->asset], $options);

        $client->request('GET', self::publicUri($share, $share->getToken()));
        $this->assertResponseStatusCodeSame(401);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $this->asset->getId(), $this->createPublicRendition()->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);

        // The owner still manages it
        $client->request('GET', self::publicUri($share, $share->getToken()), self::auth(self::USER));
        $this->assertResponseIsSuccessful();
    }

    public function testAlternateUrlsOnlyListRenditionsOfPublicPolicies(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $public = $this->createPublicRendition();
        $restricted = $this->createRestrictedRendition();
        $share = $this->createShare(self::USER, [$this->asset]);

        $data = $client->request('GET', self::publicUri($share, $share->getToken()))->toArray();

        $urls = array_column($data['alternateUrls'], null, 'name');
        $this->assertArrayHasKey('web', $urls);
        $this->assertArrayNotHasKey('hd', $urls, 'A rendition of a non-public policy is not shared');
        $this->assertSame($public->getId(), $urls['web']['definitionId']);
        $this->assertSame($this->asset->getId(), $urls['web']['assetId']);
        $this->assertStringContainsString(sprintf('/s/%s/a/%s/r/%s', $share->getId(), $this->asset->getId(), $public->getId()), $urls['web']['url']);
        $this->assertStringContainsString('token='.$share->getToken(), $urls['web']['url']);
        $this->assertNotSame($restricted->getId(), $urls['web']['definitionId']);
    }

    public function testSharedAssetExposesItsPublicPreviewToAnonymousViewers(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $public = $this->createPublicRendition();
        $restricted = $this->createRestrictedRendition();
        $em = self::getEntityManager();
        self::managed($public)->setUseAsPreview(true);
        self::managed($restricted)->setUseAsThumbnail(true);
        $em->flush();
        $share = $this->createShare(self::USER, [$this->asset]);

        // The viewer cannot read the asset: the share token alone exposes its public renditions
        $data = $client->request('GET', self::publicUri($share, $share->getToken()))->toArray();

        $this->assertSame(self::PREVIEW_URL, $data['assets'][0]['preview']['file']['url'] ?? null);
        $this->assertArrayNotHasKey('thumbnail', $data['assets'][0], 'A rendition of a non-public policy is not shared');
    }

    public function testRenditionRedirectsToTheFile(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $definition = $this->createPublicRendition();
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $this->asset->getId(), $definition->getId(), $share->getToken()));
        $this->assertResponseRedirects(self::PREVIEW_URL);
    }

    public function testRenditionOfMultiAssetShareTargetsTheRequestedAsset(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $definition = $this->createPublicRendition();
        $second = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
        ]);
        $this->createRendition($second, $definition, $this->createUrlFile($this->workspace, 'https://cdn.phrasea.test/second.jpg'));
        $share = $this->createShare(self::USER, [$this->asset, $second]);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $second->getId(), $definition->getId(), $share->getToken()));
        $this->assertResponseRedirects('https://cdn.phrasea.test/second.jpg');
    }

    public function testRenditionIsNotFoundWithoutAValidToken(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $definition = $this->createPublicRendition();
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s', $share->getId(), $this->asset->getId(), $definition->getId()));
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=invalid', $share->getId(), $this->asset->getId(), $definition->getId()));
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', '1a2b3c4d-0000-4000-8000-000000000000', $this->asset->getId(), $definition->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRenditionOfAnAssetOutsideTheShareIsNotFound(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $definition = $this->createPublicRendition();
        $outside = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
        ]);
        $this->createRendition($outside, $definition, $this->createUrlFile($this->workspace, 'https://cdn.phrasea.test/outside.jpg'));
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $outside->getId(), $definition->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMissingRenditionIsNotFound(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $share = $this->createShare(self::USER, [$this->asset]);
        // Defined in the workspace, but never generated for the asset
        $definition = $this->createRenditionDefinition($this->workspace, 'thumb', true);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $this->asset->getId(), $definition->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $this->asset->getId(), '1a2b3c4d-0000-4000-8000-000000000000', $share->getToken()));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRenditionOutsideThePublicPoliciesIsNotServed(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $restricted = $this->createRestrictedRendition();
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', sprintf('/s/%s/a/%s/r/%s?token=%s', $share->getId(), $this->asset->getId(), $restricted->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAttachmentsAreListedAndDownloadable(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $attachment = $this->createSharedAttachment($this->asset, 'Contract');
        $share = $this->createShare(self::USER, [$this->asset]);
        // The in-memory asset does not know its new attachment
        self::getEntityManager()->clear();

        $data = $client->request('GET', self::publicUri($share, $share->getToken()))->toArray();
        $this->assertCount(1, $data['attachments']);
        $item = $data['attachments'][0];
        $this->assertSame($attachment->getId(), $item['id']);
        $this->assertSame('Contract', $item['name']);
        $this->assertSame($this->asset->getId(), $item['assetId']);
        // The shared rendition, not the original
        $this->assertSame('image/jpeg', $item['type']);
        $this->assertStringContainsString(sprintf('/s/%s/a/%s', $share->getId(), $attachment->getId()), $item['url']);

        $client->request('GET', sprintf('/s/%s/a/%s?token=%s', $share->getId(), $attachment->getId(), $share->getToken()));
        $this->assertResponseRedirects(self::ATTACHMENT_PREVIEW_URL);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function undeliverableAttachmentProvider(): iterable
    {
        yield 'trashed' => ['trashed'];
        yield 'quarantined' => ['quarantined'];
        yield 'not readable by the owner of the share' => ['unreadable'];
        yield 'without a rendition of a public policy' => ['no_public_rendition'];
    }

    /**
     * Evaluated at each access, from the attached asset: a share link never delivers its original.
     */
    #[DataProvider('undeliverableAttachmentProvider')]
    public function testUndeliverableAttachmentIsNeitherListedNorServed(string $case): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $em = self::getEntityManager();
        switch ($case) {
            case 'unreadable':
                // A secret asset of a workspace the owner of the share has no access to
                $foreign = $this->createNamedWorkspace(self::OTHER, 'foreign-ws');
                $attachment = $this->createSharedAttachment($this->asset, 'Contract', $foreign, self::OTHER);
                break;
            case 'no_public_rendition':
                $attachment = $this->createAttachmentWithFile($this->asset, 'Contract', self::ATTACHMENT_ORIGINAL_URL);
                $restricted = $this->createRenditionDefinition($this->workspace, 'attachment-hd', false);
                $this->createRendition($attachment->getAttachment(), $restricted, $this->createUrlFile($this->workspace, self::ORIGINAL_URL, 'image/tiff'));
                break;
            default:
                $attachment = $this->createSharedAttachment($this->asset, 'Contract');
                $attached = self::managed($attachment->getAttachment());
                if ('trashed' === $case) {
                    $attached->setDeletedAt(new \DateTimeImmutable());
                } else {
                    $attached->setStatus(AssetStatusEnum::Quarantined);
                }
                $em->flush();
        }
        $share = $this->createShare(self::USER, [$this->asset]);
        $em->clear();

        $data = $client->request('GET', self::publicUri($share, $share->getToken()))->toArray();
        $this->assertSame([], $data['attachments']);

        $client->request('GET', sprintf('/s/%s/a/%s?token=%s', $share->getId(), $attachment->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAttachmentRequiresAValidToken(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $attachment = $this->createSharedAttachment($this->asset, 'Contract');
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', sprintf('/s/%s/a/%s', $share->getId(), $attachment->getId()));
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/s/%s/a/%s?token=invalid', $share->getId(), $attachment->getId()));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAttachmentOfAnAssetOutsideTheShareIsNotFound(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $outside = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
        ]);
        $foreignAttachment = $this->createSharedAttachment($outside, 'Secret');
        $share = $this->createShare(self::USER, [$this->asset]);

        $client->request('GET', sprintf('/s/%s/a/%s?token=%s', $share->getId(), $foreignAttachment->getId(), $share->getToken()));
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/s/%s/a/%s?token=%s', $share->getId(), '1a2b3c4d-0000-4000-8000-000000000000', $share->getToken()));
        $this->assertResponseStatusCodeSame(404);
    }

    private function createPublicRendition(): RenditionDefinition
    {
        $definition = $this->createRenditionDefinition($this->workspace, 'web', true);
        $this->createRendition($this->asset, $definition, $this->createUrlFile($this->workspace, self::PREVIEW_URL));

        return $definition;
    }

    private function createRestrictedRendition(): RenditionDefinition
    {
        $definition = $this->createRenditionDefinition($this->workspace, 'hd', false);
        $this->createRendition($this->asset, $definition, $this->createUrlFile($this->workspace, self::ORIGINAL_URL, 'image/tiff'));

        return $definition;
    }

    private function createAttachmentWithFile(Asset $asset, string $name, string $url, ?Workspace $workspace = null, string $ownerId = self::USER): AssetAttachment
    {
        $workspace ??= $this->workspace;
        $attached = $this->createAsset([
            'workspace' => self::managed($workspace),
            'ownerId' => $ownerId,
            'no_flush' => true,
        ]);
        $attached->setSource(self::managed($this->createUrlFile($workspace, $url, 'application/pdf')));
        self::getEntityManager()->flush();

        return $this->createAssetAttachment($asset, $attached, $name);
    }

    /**
     * An attachment whose asset has a rendition of a public policy besides its original.
     */
    private function createSharedAttachment(Asset $asset, string $name, ?Workspace $workspace = null, string $ownerId = self::USER): AssetAttachment
    {
        $workspace ??= $this->workspace;
        $attachment = $this->createAttachmentWithFile($asset, $name, self::ATTACHMENT_ORIGINAL_URL, $workspace, $ownerId);
        $definition = $this->createRenditionDefinition($workspace, 'attachment-preview', true);
        $this->createRendition($attachment->getAttachment(), $definition, $this->createUrlFile($workspace, self::ATTACHMENT_PREVIEW_URL));

        return $attachment;
    }
}
