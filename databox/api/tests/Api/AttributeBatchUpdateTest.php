<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Entity\Core\Asset;
use App\Tests\AbstractSearchTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AttributeBatchUpdateTest extends AbstractSearchTestCase
{
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
        $assetsIds = array_map(fn (array $r): string => $r['id'], $em->getRepository(Asset::class)->createQueryBuilder('a')
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
                        'name' => 'description',
                        'value' => 'Foo bar',
                    ],
                    [
                        'name' => 'keywords',
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
