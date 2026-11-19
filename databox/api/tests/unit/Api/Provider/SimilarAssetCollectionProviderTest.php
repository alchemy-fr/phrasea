<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Api\Provider\SimilarAssetCollectionProvider;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class SimilarAssetCollectionProviderTest extends TestCase
{
    public function testScoresOnlyCoverTheReturnedAssets(): void
    {
        $reference = new Asset();
        $readable = new Asset();
        $denied = new Asset();

        $repository = $this->createStub(AssetRepository::class);
        $repository->method('find')->willReturn($reference);

        $similarAssetSearch = $this->createMock(SimilarAssetSearch::class);
        $similarAssetSearch->expects($this->once())
            ->method('findSimilar')
            ->with('u1', ['g1'], $reference, 10)
            ->willReturn([
                [$readable, $denied],
                [$readable->getId() => 0.9, $denied->getId() => 0.8],
            ]);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new JwtUser('jwt', 'u1', 'user', groups: ['g1']));
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute, mixed $subject = null): bool => $subject !== $denied
        );

        $provider = new SimilarAssetCollectionProvider($repository, $similarAssetSearch);
        $provider->setSecurity($security);

        $result = $provider->provide(new GetCollection(), ['id' => $reference->getId()]);

        $this->assertInstanceOf(ApiMetaWrapperOutput::class, $result);
        $this->assertSame([$readable], iterator_to_array($result->getResult()));
        $this->assertSame([$readable->getId() => 0.9], $result->getMeta()['scores']);
    }
}
