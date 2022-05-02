<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Core\Asset;
use App\Tests\FixturesTrait;
use App\Tests\Search\SearchTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AttributeBatchUpdateTest extends ApiTestCase
{
    use FixturesTrait;
    use SearchTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::fixturesBootKernel($options);
        self::bootSearch(static::$kernel);

        return static::$kernel;
    }

    public function testAttributesBatchUpdateWithInvalidValue(): void
    {
        $this->batchAction([
            [
                'name' => 'Keywords',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAttributesBatchUpdateWithNoAction(): void
    {
        $this->batchAction([]);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAttributesBatchUpdateWithInvalidAttributeName(): void
    {
        $this->batchAction([
            [
                'name' => 'Undefined',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAttributesBatchUpdateWithInvalidAttributeId(): void
    {
        $this->batchAction([
            [
                'id' => '123',
                'value' => 'Foo',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @dataProvider getCases
     */
    public function testAttributesBatchUpdateOK(array $actions, array $expectedAssets): void
    {
        $response = $this->batchAction($actions);
        $this->assertEmpty($response->getContent());
        $this->assertResponseStatusCodeSame(200);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($expectedAssets as $key => $expectedValues) {
            ksort($expectedValues);
            $attrAssertions = [];
            foreach ($expectedValues as $name => $value) {
                $attrAssertions[] = [
                    'definition' => [
                        'name' => $name,
                    ],
                    'value' => $value,
                ];
            }

            $asset = $em->getRepository(Asset::class)->findOneBy([
                'key' => $key,
            ]);
            static::createClient()->request('GET', '/assets/'.$asset->getId(), [
                'headers' => [
                    'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
                ],
            ]);

            $this->assertJsonContains([
                '@type' => 'asset',
                'attributes' => $attrAssertions,
            ]);
        }

        $this->assertResponseIsSuccessful();
    }

    private function batchAction(array $actions): ResponseInterface
    {
        self::enableFixtures();
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $assetsIds = array_map(function (array $r): string {
            return $r['id'];
        }, $em->getRepository(Asset::class)->createQueryBuilder('a')
            ->select('a.id')
            ->andWhere('a.key IS NOT NULL')
            ->getQuery()
            ->getScalarResult());

        return $client->request('POST', '/attributes/batch-update', [
            'headers' => [
                'Authorization' => 'Bearer '.AuthServiceClientTestMock::ADMIN_TOKEN,
            ],
            'json' => [
                'actions' => $actions,
                'assets' => $assetsIds,
            ],
        ]);
    }

    public function getCases(): array
    {
        return [
            [
                [
                    [
                        'name' => 'Description',
                        'value' => 'Foo bar',
                    ],
                    [
                        'name' => 'Keywords',
                        'value' => ['This is KW #1'],
                    ],
                ], [
                    'foo' => ['Description' => 'Foo bar', 'Keywords' => ['This is KW #1']],
                    'bar' => ['Description' => 'Foo bar', 'Keywords' => ['This is KW #1']],
                ],
            ],
        ];
    }
}
