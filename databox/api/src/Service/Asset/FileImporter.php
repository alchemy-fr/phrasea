<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\StorageBundle\Upload\FileValidator;
use App\Border\Exception\UnsupportedUriException;
use App\Entity\Core\File;
use App\Service\Asset\Exception\FileImportRefusedException;
use App\Service\Asset\Exception\RemoteFileNotFoundException;
use App\Service\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Header;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        private FileValidator $fileValidator,
    ) {
    }

    /**
     * Errors that retrying never fixes are logged and leave the file untouched
     * (still referenced by URL); transient ones are thrown.
     *
     * @throws FileImportRefusedException  when the content is refused: the file is rejected
     * @throws RemoteFileNotFoundException when the URL no longer serves the file
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
            if (\in_array($e->getResponse()->getStatusCode(), [404, 410], true)) {
                throw new RemoteFileNotFoundException($file, $e);
            }

            throw $e;
        } catch (UnsupportedUriException $e) {
            // The source has to be fixed by hand; retrying never helps.
            $this->logger->error(sprintf('Import error: file "%s": %s', $file->getId(), $e->getMessage()));

            return;
        }

        try {
            $mimeType = null;
            // Header names are lower-cased by the HTTP client
            if (isset($headers['content-type'][0])) {
                $type = Header::parse($headers['content-type'][0]);
                if (null === $file->getType() && !empty($type[0][0])) {
                    $mimeType = $type[0][0];
                }
            }

            try {
                $contentType = $this->fileValidator->validateFileContent(
                    $src,
                    $file->getOriginalName() ?? $file->getPath(),
                    $mimeType ?? $file->getType(),
                );
            } catch (BadRequestHttpException $e) {
                $file->rejectContent($e->getMessage());
                $this->em->persist($file);
                $this->em->flush();

                throw new FileImportRefusedException($file, $e);
            }

            if (null !== $mimeType) {
                $mimeType = $contentType->type;
                $file->setType($mimeType);
            }
            $file->setDetectedType($contentType->detectedType);
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
