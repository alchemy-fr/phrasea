<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Entity\Core\File;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * The grid shows the region of interest of the assets: it is listed.
 */
final class AssetRoiSearchTest extends AbstractSearchTestCase
{
    public function testRoiIsListed(): void
    {
        $workspace = $this->createWorkspace([
            'no_acl' => true,
            'public' => true,
            'no_flush' => true,
        ]);
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_URL);
        $file->setPath('https://example.com/photo.png');
        $file->setPathPublic(true);
        $file->setType('image/png');
        $em->persist($file);

        $roi = ['crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]];
        $withRoi = $this->createAsset(['public' => true, 'workspace' => $workspace, 'no_flush' => true]);
        $withRoi->setSource($file);
        $withRoi->setRoi($roi);
        $without = $this->createAsset(['public' => true, 'workspace' => $workspace]);

        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $response = self::createClient()->request('GET', '/assets');
        $items = $this->getDataFromResponse($response, 200)['member'];
        $byId = array_column($items, null, 'id');

        $this->assertCount(2, $byId);
        $this->assertEquals($roi, $byId[$withRoi->getId()]['roi']);
        $this->assertArrayNotHasKey('roi', $byId[$without->getId()]);
    }
}
