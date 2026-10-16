<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Border\Exception\UnsupportedUriException;
use App\Entity\Core\File;
use App\Service\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Header;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

/**
 * Fetch the content of a file referenced by URL and store it on the main storage.
 */
readonly class FileImporter
{
    public function __construct(
        private FileManager $fileManager,
        private FileFetcher $fileFetcher,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Errors that retrying never fixes are logged and leave the file untouched
     * (still referenced by URL); transient ones are thrown.
     */
    public function importFile(File $file): void
    {
        if (!$file->isPathPublic()) {
            // Declared private by the client: the URL cannot be fetched, retrying never helps.
            $this->logger->error(sprintf('Import error: Source of file "%s" is not publicly accessible', $file->getId()));

            return;
        }

        if (File::STORAGE_URL !== $file->getStorage()) {
            $this->logger->error(sprintf('Import error: Storage of file "%s" should be "%s"', $file->getId(), File::STORAGE_URL));

            // File may have already been imported
            return;
        }

        $headers = [];

        try {
            $src = $this->fileFetcher->downloadFile($file, $headers);
        } catch (ClientException $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                $this->logger->error($e->getMessage());

                return;
            }

            throw $e;
        } catch (UnsupportedUriException $e) {
            // The source has to be fixed by hand; retrying never helps.
            $this->logger->error(sprintf('Import error: file "%s": %s', $file->getId(), $e->getMessage()));

            return;
        }

        try {
            $mimeType = null;
            if (isset($headers['Content-Type'])) {
                $type = Header::parse($headers['Content-Type']);
                if (null === $file->getType() && !empty($type)) {
                    $mimeType = $type[0][0];
                    $file->setType($mimeType);
                }
            }

            $file->setSize(filesize($src));

            $finalPath = $this->fileManager->storeFile(
                $file->getWorkspace(),
                $src,
                $mimeType,
                $file->getExtension(),
                null
            );

            $file->setPath($finalPath);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setPathPublic(true);
            $file->setImportPending(false);
            $this->em->persist($file);
            $this->em->flush();
        } finally {
            if (file_exists($src)) {
                unlink($src);
            }
        }
    }
}
