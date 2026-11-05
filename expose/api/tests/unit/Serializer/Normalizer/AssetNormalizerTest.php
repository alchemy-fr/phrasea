<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use App\Serializer\Normalizer\AssetNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetNormalizerTest extends TestCase
{
    private ?TokenInterface $token = null;
    private int $votes = 0;

    public function testReadDetailsIsVotedOncePerPublicationAndToken(): void
    {
        $normalizer = new AssetNormalizer($this->createSecurity());
        $publication = new Publication();
        $otherPublication = new Publication();

        foreach ([$publication, $publication, $otherPublication, $publication] as $assetPublication) {
            $this->normalizeAsset($normalizer, $assetPublication);
        }
        $this->assertSame(2, $this->votes);

        $this->token = new NullToken();
        $this->normalizeAsset($normalizer, $publication);
        $this->assertSame(3, $this->votes);

        $normalizer->reset();
        $this->normalizeAsset($normalizer, $publication);
        $this->assertSame(4, $this->votes);
    }

    private function normalizeAsset(AssetNormalizer $normalizer, Publication $publication): void
    {
        $asset = new Asset();
        $asset->setPublication($publication);
        $context = ['groups' => [Publication::GROUP_READ]];

        $normalizer->normalize($asset, $context);
    }

    private function createSecurity(): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getToken')->willReturnCallback(fn (): ?TokenInterface => $this->token);
        $security->method('isGranted')->willReturnCallback(function (mixed $attribute): bool {
            $this->assertSame(PublicationVoter::READ_DETAILS, $attribute);
            ++$this->votes;

            return false;
        });

        return $security;
    }
}
