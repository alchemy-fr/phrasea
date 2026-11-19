<?php

declare(strict_types=1);

namespace App\Tests\Functional\Storage;

use App\Entity\Core\AssetImport;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Service\Storage\FileManager;
use App\Tests\Functional\AbstractDataboxTestCase;

class FileManagerTest extends AbstractDataboxTestCase
{
    public function testShortPathOfMainStorageFileIsReferenced(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $path = 'test/'.uniqid().'.jpg';
        $file = $this->createFile($workspace, $path, File::STORAGE_S3_MAIN);
        self::getEntityManager()->flush();

        $fileManager = self::getService(FileManager::class);
        $this->assertTrue($fileManager->isPathReferenced($path));
        $this->assertFalse($fileManager->isPathReferenced($path, $file->getId()));
    }

    public function testLongPathOfMainStorageFileIsReferenced(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $path = 'test/'.str_repeat('a', File::INDEXED_PATH_MAX_LENGTH).'.jpg';
        $file = $this->createFile($workspace, $path, File::STORAGE_S3_MAIN);
        self::getEntityManager()->flush();

        $fileManager = self::getService(FileManager::class);
        $this->assertTrue($fileManager->isPathReferenced($path));
        $this->assertFalse($fileManager->isPathReferenced($path, $file->getId()));
    }

    public function testPathOfUrlFileIsNotReferenced(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $path = 'test/'.uniqid().'.jpg';
        $this->createFile($workspace, $path, File::STORAGE_URL);
        self::getEntityManager()->flush();

        $this->assertFalse(self::getService(FileManager::class)->isPathReferenced($path));
    }

    public function testSourcePathOfAssetImportIsReferenced(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $path = 'imports/'.uniqid().'.ndjson';

        $import = new AssetImport();
        $import->setWorkspace($workspace);
        $import->setOwnerId('owner');
        $import->setSourcePath($path);
        $em = self::getEntityManager();
        $em->persist($import);
        $em->flush();

        $this->assertTrue(self::getService(FileManager::class)->isPathReferenced($path));
    }

    private function createFile(Workspace $workspace, string $path, string $storage): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage($storage);
        $file->setPath($path);
        self::getEntityManager()->persist($file);

        return $file;
    }
}
