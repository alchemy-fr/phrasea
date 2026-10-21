<?php

declare(strict_types=1);

namespace App\Archive;

use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\CoreBundle\Util\StringUtil;
use Alchemy\CoreBundle\Util\ZipUtil;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\DeleteAsset;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the ZIP archive of a publication's assets and keeps it in the asset storage,
 * until the content it was built from changes.
 */
final readonly class PublicationArchiver
{
    private const int MAX_NAME_LENGTH = 150;

    private HttpClientInterface $client;

    public function __construct(
        private EntityManagerInterface $em,
        private FileStorageManager $storageManager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        HttpClientInterface $client,
    ) {
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    /**
     * Returns the storage path of the publication archive, building it first if needed.
     */
    public function getArchivePath(Publication $publication): string
    {
        if ($publication->getAssets()->isEmpty()) {
            throw new \LogicException(sprintf('Publication "%s" has no asset to archive.', $publication->getId()));
        }

        $hash = $this->getArchiveHash($publication);
        $currentPath = $publication->getArchivePath();
        if ($hash === $publication->getArchiveHash()
            && null !== $currentPath
            && $this->storageManager->has($currentPath)) {
            return $currentPath;
        }

        // The path only depends on the content: concurrent builds of the same content write the same file.
        $archivePath = sprintf('archives/%s/%s/%s.zip', $publication->getId(), $hash, $this->getArchiveName($publication));
        $this->buildArchive($publication, $archivePath);

        $publication = $this->em->find(Publication::class, $publication->getId())
            ?? throw new \RuntimeException(sprintf('Publication "%s" no longer exists.', $publication->getId()));
        $previousPath = $publication->getArchivePath();
        $publication->setArchivePath($archivePath);
        $publication->setArchiveHash($hash);
        $this->em->flush();

        if (null !== $previousPath && $previousPath !== $archivePath) {
            $this->bus->dispatch(new DeleteAsset($previousPath));
        }

        return $archivePath;
    }

    private function buildArchive(Publication $publication, string $archivePath): void
    {
        $workDir = sys_get_temp_dir().'/'.uniqid('publication-archive', true);
        $zipFile = $workDir.'.zip';
        mkdir($workDir, 0755, true);

        try {
            $usedNames = [];
            foreach ($publication->getAssets() as $asset) {
                $destination = $workDir.'/'.$this->getUniqueName(basename($asset->getOriginalName()), $usedNames);
                $this->copyStream($this->storageManager->getStream($asset->getPath()), $destination);
            }

            if ($publication->isIncludeDownloadTermsInArchive()
                && null !== $termsUrl = $publication->getDownloadTerms()->getUrl()) {
                $termsName = basename((string) parse_url($termsUrl, PHP_URL_PATH));
                $this->downloadTerms($termsUrl, $workDir.'/'.$this->getUniqueName($termsName ?: 'terms', $usedNames));
            }

            ZipUtil::zipDirectory($workDir, $zipFile);

            $fd = fopen($zipFile, 'r');
            if (false === $fd) {
                throw new \RuntimeException(sprintf('Unable to open file "%s"', $zipFile));
            }
            try {
                $this->storageManager->storeStream($archivePath, $fd);
            } finally {
                if (is_resource($fd)) {
                    fclose($fd);
                }
            }
        } finally {
            FilesystemUtils::rrmdir($workDir);
            if (is_file($zipFile)) {
                unlink($zipFile);
            }
        }
    }

    /**
     * @param resource $source
     */
    private function copyStream($source, string $destination): void
    {
        $fd = fopen($destination, 'w');
        if (false === $fd) {
            throw new \RuntimeException(sprintf('Unable to open file "%s"', $destination));
        }

        try {
            if (false === stream_copy_to_stream($source, $fd)) {
                throw new \RuntimeException(sprintf('Unable to write file "%s"', $destination));
            }
        } finally {
            fclose($fd);
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }

    private function downloadTerms(string $url, string $destination): void
    {
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 10,
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('Unexpected HTTP status %d', $response->getStatusCode()));
            }

            $fd = fopen($destination, 'w');
            if (false === $fd) {
                throw new \RuntimeException(sprintf('Unable to open file "%s"', $destination));
            }
            try {
                foreach ($this->client->stream($response) as $chunk) {
                    fwrite($fd, $chunk->getContent());
                }
            } finally {
                fclose($fd);
            }
        } catch (HttpClientExceptionInterface|\RuntimeException $e) {
            if (is_file($destination)) {
                unlink($destination);
            }

            $this->logger->warning(sprintf('Download terms "%s" could not be added to the publication archive: %s', $url, $e->getMessage()), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * @param array<string, true> $usedNames
     */
    private function getUniqueName(string $name, array &$usedNames): string
    {
        if ('' === $name || '.' === $name || '..' === $name) {
            $name = 'file';
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $baseName = '' !== $extension ? substr($name, 0, -strlen($extension) - 1) : $name;

        $candidate = $name;
        for ($i = 2; isset($usedNames[mb_strtolower($candidate)]); ++$i) {
            $candidate = sprintf('%s (%d)%s', $baseName, $i, '' !== $extension ? '.'.$extension : '');
        }
        $usedNames[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    private function getArchiveName(Publication $publication): string
    {
        $name = substr(StringUtil::slugify($publication->getTitle() ?? ''), 0, self::MAX_NAME_LENGTH);

        return '' !== $name ? $name : 'publication-'.$publication->getId();
    }

    private function getArchiveHash(Publication $publication): string
    {
        $includeTerms = $publication->isIncludeDownloadTermsInArchive();
        $parts = [
            $includeTerms ? 'include_terms' : 'exclude_terms',
            $publication->getTitle(),
        ];

        if ($includeTerms) {
            $parts[] = $publication->getDownloadTerms()->getUrl();
        }

        foreach ($publication->getAssets() as $asset) {
            $parts[] = sprintf('%s-%s-%s-%s',
                $asset->getSlug(),
                $asset->getOriginalName(),
                $asset->getSize(),
                $asset->getPath(),
            );
        }

        return md5(implode(',', $parts));
    }
}
