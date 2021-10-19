<?php

declare(strict_types=1);

namespace App\Tests\Asset;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Storage\SubDefinitionManager;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractAssetTest extends ApiTestCase
{
    const SAMPLE_FILE = __DIR__.'/../fixtures/32x32.jpg';
    protected $assetId;

    protected function commitAsset(string $token = 'secret_token')
    {
        $commit = new Commit();
        $commit->setToken($token);
        $commit->setUserId('a_user_id');
        $commit->setFormData(['foo' => 'bar']);
        $commit->setTotalSize(42);

        /** @var EntityManagerInterface $em */
        $em = self::$container->get(EntityManagerInterface::class);
        $em->persist($commit);
        $em->flush();
        $em
            ->getRepository(Asset::class)
            ->attachCommit([$this->assetId], $commit->getId());

        $asset = $em->find(Asset::class, $this->assetId);
        $em->refresh($asset);
    }

    private function createAsset(): Asset
    {
        /** @var SubDefinitionManager $assetManager */
        $assetManager = self::$container->get(SubDefinitionManager::class);
        $path = 'test/foo.jpg';

        return $assetManager->createAsset($path, 'image/jpeg', 'foo.jpg', 846, 'user_id');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $asset = $this->createAsset();
        $this->assetId = $asset->getId();
    }
}
