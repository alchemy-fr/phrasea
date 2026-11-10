<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use App\Entity\Publication;
use App\Security\DownloadMode;
use App\Security\PublicationDownloadPolicy;
use App\Security\Voter\PublicationVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PublicationDownloadPolicyTest extends TestCase
{
    private const string URI = 'https://expose.test/publications/p/assets/a/download';

    private bool $editor = false;
    private bool $readDetails = true;

    public function testUriTokenCarriesTheExtraClaims(): void
    {
        $manager = $this->createUriJwtManager();
        $signed = $manager->signUri(self::URI, 60, ['dl' => 'email']);
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        $this->assertSame('email', $manager->validateUriToken($signed, $query['jwt'])->claims()->get('dl'));

        $this->expectException(AccessDeniedHttpException::class);
        $manager->validateUriToken(str_replace('/a/', '/b/', $signed), $query['jwt']);
    }

    #[DataProvider('provideModes')]
    public function testMode(array $config, bool $editor, bool $readDetails, DownloadMode $expected): void
    {
        $this->editor = $editor;
        $this->readDetails = $readDetails;

        $this->assertSame($expected, $this->createPolicy()->getMode($this->createPublication($config)));
    }

    public static function provideModes(): iterable
    {
        yield 'disabled by default' => [[], false, true, DownloadMode::None];
        yield 'editor' => [[], true, false, DownloadMode::Editor];
        yield 'without the details' => [['enabled' => true], false, false, DownloadMode::None];
        yield 'direct' => [['enabled' => true], false, true, DownloadMode::Direct];
        yield 'terms' => [['enabled' => true, 'terms' => true], false, true, DownloadMode::Terms];
        yield 'email' => [['enabled' => true, 'email' => true, 'terms' => true], false, true, DownloadMode::Email];
    }

    #[DataProvider('provideDirectDownloads')]
    public function testDirectDownload(array $config, ?string $claim, bool $granted): void
    {
        $policy = $this->createPolicy();
        $uri = null === $claim
            ? $this->createUriJwtManager()->signUri(self::URI)
            : $this->createUriJwtManager()->signUri(self::URI, null, [PublicationDownloadPolicy::CLAIM => $claim]);

        if (!$granted) {
            $this->expectException(AccessDeniedHttpException::class);
        }
        $policy->denyDirectDownloadUnlessGranted($this->createPublication($config), Request::create($uri));
        $this->addToAssertionCount(1);
    }

    public static function provideDirectDownloads(): iterable
    {
        yield 'direct, unsigned for download' => [['enabled' => true], null, false];
        yield 'direct, direct URL' => [['enabled' => true], 'direct', true];
        yield 'direct, email URL' => [['enabled' => true], 'email', true];
        yield 'direct, other claim' => [['enabled' => true], 'other', false];
        yield 'email, direct URL' => [['enabled' => true, 'email' => true], 'direct', false];
        yield 'email, email URL' => [['enabled' => true, 'email' => true], 'email', true];
        yield 'terms, unsigned for download' => [['enabled' => true, 'terms' => true], null, false];
        yield 'terms, direct URL' => [['enabled' => true, 'terms' => true], 'direct', true];
        yield 'disabled, email URL' => [[], 'email', false];
    }

    public function testUnsignedDirectDownloadIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->createPolicy()->denyDirectDownloadUnlessGranted($this->createPublication(['enabled' => true]), Request::create(self::URI));
    }

    public function testEditorDownloadsWithoutSignature(): void
    {
        $this->editor = true;
        $this->createPolicy()->denyDirectDownloadUnlessGranted($this->createPublication([]), Request::create(self::URI));
        $this->addToAssertionCount(1);
    }

    public function testEmailRequestNeedsTheTermsAccepted(): void
    {
        $publication = $this->createPublication(['enabled' => true, 'email' => true, 'terms' => true]);
        $policy = $this->createPolicy();

        $policy->denyEmailRequestUnlessGranted($publication, $this->createPostRequest(['acceptTerms' => true]));

        $this->expectException(BadRequestHttpException::class);
        $policy->denyEmailRequestUnlessGranted($publication, $this->createPostRequest(['acceptTerms' => 'true']));
    }

    public function testDownloadUrlOfAPublicationDownloadedViaEmail(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->createPolicy()->denyDownloadUrlUnlessGranted(
            $this->createPublication(['enabled' => true, 'email' => true]),
            $this->createPostRequest(['acceptTerms' => true]),
        );
    }

    public function testModeIsResolvedOncePerPublication(): void
    {
        $votes = 0;
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(function () use (&$votes): bool {
            ++$votes;

            return false;
        });
        $policy = new PublicationDownloadPolicy($security, $this->createUriJwtManager());
        $publication = $this->createPublication(['enabled' => true]);

        $policy->getMode($publication);
        $policy->getMode($publication);
        $this->assertSame(2, $votes);

        $policy->reset();
        $policy->getMode($publication);
        $this->assertSame(4, $votes);
    }

    private function createPolicy(): PublicationDownloadPolicy
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(fn (mixed $attribute): bool => match ($attribute) {
            AbstractVoter::EDIT => $this->editor,
            PublicationVoter::READ_DETAILS => $this->readDetails,
            default => false,
        });

        return new PublicationDownloadPolicy($security, $this->createUriJwtManager());
    }

    private function createUriJwtManager(): UriJwtManager
    {
        return new UriJwtManager('test-secret-of-at-least-256-bits-for-hs256!', 'https://expose.test', 3600);
    }

    private function createPublication(array $config): Publication
    {
        $publication = new Publication();
        $publicationConfig = $publication->getConfig();
        $publicationConfig->setDownloadEnabled($config['enabled'] ?? null);
        $publicationConfig->setDownloadViaEmail($config['email'] ?? null);
        $publicationConfig->getDownloadTerms()->setEnabled($config['terms'] ?? null);

        return $publication;
    }

    private function createPostRequest(array $body): Request
    {
        return Request::create(self::URI, 'POST', $body);
    }
}
