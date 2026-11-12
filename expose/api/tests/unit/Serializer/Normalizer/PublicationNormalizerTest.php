<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use Alchemy\AuthBundle\Security\UriJwtManager;
use App\Entity\Publication;
use App\Security\PublicationDownloadPolicy;
use App\Security\Voter\PublicationVoter;
use App\Serializer\Normalizer\PublicationNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicationNormalizerTest extends TestCase
{
    public function testTheChildrenAreFilteredWithoutChangingTheEntity(): void
    {
        $parent = new Publication();
        $visible = new Publication();
        $hidden = new Publication();
        $parent->addChild($visible);
        $parent->addChild($hidden);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            fn (mixed $attribute, mixed $subject = null): bool => match ($attribute) {
                PublicationVoter::READ => $subject !== $hidden,
                PublicationVoter::READ_DETAILS => true,
                default => false,
            }
        );

        $uriJwtManager = new UriJwtManager(str_repeat('s', 32), 'https://expose.test', 3600);
        $normalizer = new PublicationNormalizer($security);
        $normalizer->setDownloadPolicy(new PublicationDownloadPolicy($security, $uriJwtManager));
        $normalizer->setUriJwtManager($uriJwtManager);
        $normalizer->setUrlGenerator($this->createStub(UrlGeneratorInterface::class));

        $this->assertSame(2, $parent->getChildrenCount());

        $context = ['groups' => [Publication::GROUP_READ]];
        $normalizer->normalize($parent, $context);

        $this->assertSame([$visible], $parent->getVisibleChildren());
        $this->assertSame(1, $parent->getChildrenCount());
        $this->assertSame([$visible, $hidden], $parent->getChildren()->getValues());
    }
}
