<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\Zippy\Zippy;

final readonly class ArchiveManager
{
    public function __construct()
    {
    }

    public function buildArchive(array $files): void
    {
        $archiveDir = sys_get_temp_dir().'/'.uniqid('archive-file');

        try {
            if (is_dir($archiveDir)) {
                FilesystemUtils::rrmdir($archiveDir);
            }

            mkdir($archiveDir, 0755, true);

            $zippy = Zippy::load();
            $zippy->create($this->getArchivePath($archive), [
                'content' => $dest,
            ], true);
        } finally {
            FilesystemUtils::rrmdir($dest);
        }
    }

    public function getArchivePath(Archive $archive): string
    {
        return $this->dataDir.DIRECTORY_SEPARATOR.$archive->getId().'.zip';
    }

    public function deleteArchive(Archive $archive): void
    {
        $dir = $this->getArchiveDataDir($archive);
        if (is_dir($dir)) {
            FilesystemUtils::rrmdir($dir);
        }

        $this->em->remove($archive);
        $this->em->flush();
    }

    public function getArchive(string $id): ?Archive
    {
        return $this->em->find(Archive::class, $id);
    }

    private function getArchiveDataDir(Archive $archive): string
    {
        return $this->dataDir.DIRECTORY_SEPARATOR.$archive->getId();
    }
}
