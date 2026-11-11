<?php

declare(strict_types=1);

namespace App\Archive;

use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\CoreBundle\Util\StringUtil;
use Alchemy\CoreBundle\Util\ZipUtil;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\DeleteAsset;
use App\Consumer\Handler\PublicationArchiveRequest;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the ZIP archive of a publication's assets and keeps it in the asset storage,
 * until the content it was built from changes.
 *
 * Only the workers build the archive (PublicationArchiveRequest): a build copies every asset
 * of the publication.
 */
final readonly class PublicationArchiver
{
    private const int MAX_NAME_LENGTH = 150;

    /**
     * How long a build request keeps the next readers of the same content from asking again.
     */
    private const float BUILD_REQUEST_TTL = 300.0;

    /**
     * Lifetime of the build lock, extended after each file added to the archive.
     */
    private const float BUILD_TTL = 300.0;

    private HttpClientInterface $client;

    public function __construct(
        private EntityManagerInterface $em,
        private FileStorageManager $storageManager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private LockFactory $lockFactory,
        HttpClientInterface $client,
    ) {
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    /**
     * The storage path of the archive, when it was built from the current content of the publication.
     */
    public function getUpToDateArchivePath(Publication $publication): ?string
    {
        $currentPath = $publication->getArchivePath();
        if (null !== $currentPath
            && $this->getArchiveHash($publication) === $publication->getArchiveHash()
            && $this->storageManager->has($currentPath)) {
            return $currentPath;
        }

        return null;
    }

    /**
     * Asks the workers to build the archive of the current content, once for all the readers
     * asking for it meanwhile.
     */
    public function requestBuild(Publication $publication): void
    {
        $lock = $this->lockFactory->createLock(
            sprintf('publication-archive-request-%s-%s', $publication->getId(), $this->getArchiveHash($publication)),
            self::BUILD_REQUEST_TTL,
            // Left to expire: the build outlives the request.
            false,
        );

        if ($lock->acquire()) {
            $this->bus->dispatch(new PublicationArchiveRequest($publication->getId()));
        }
    }

    /**
     * Builds the archive of the current content unless it is up to date, one build per
     * publication at a time.
     */
    public function buildArchive(Publication $publication): void
    {
        if ($publication->getAssets()->isEmpty()) {
            throw new \LogicException(sprintf('Publication "%s" has no asset to archive.', $publication->getId()));
        }

        $lock = $this->lockFactory->createLock('publication-archive-build-'.$publication->getId(), self::BUILD_TTL);
        $lock->acquire(true);

        try {
            // Another build may have been recorded while waiting for the lock.
            $this->em->refresh($publication);
            if (null !== $this->getUpToDateArchivePath($publication)) {
                return;
            }

            $previousHash = $publication->getArchiveHash();
            $hash = $this->getArchiveHash($publication);
            // The path only depends on the content: concurrent builds of the same content write the same file.
            $archivePath = sprintf('archives/%s/%s/%s.zip', $publication->getId(), $hash, $this->getArchiveName($publication));
            $this->buildArchiveFile($publication, $archivePath, $lock);

            $this->recordArchive($publication, $archivePath, $hash, $previousHash);
        } finally {
            $lock->release();
        }
    }

    /**
     * Records the archive built from the content hashed $hash, unless another archive was
     * recorded since $previousHash was read: a build outlived by a more recent one does not
     * replace it.
     *
     * @return bool whether the archive was recorded
     */
    public function recordArchive(Publication $publication, string $archivePath, string $hash, ?string $previousHash): bool
    {
        $hashCondition = null === $previousHash ? 'p.archiveHash IS NULL' : 'p.archiveHash = :previousHash';

        $query = $this->em->createQueryBuilder()
            ->select('p.archivePath')
            ->from(Publication::class, 'p')
            ->andWhere('p.id = :id')
            ->andWhere($hashCondition)
            ->setParameter('id', $publication->getId());
        if (null !== $previousHash) {
            $query->setParameter('previousHash', $previousHash);
        }
        $previous = $query->getQuery()->getOneOrNullResult();

        $recorded = false;
        if (null !== $previous) {
            $query = $this->em->createQueryBuilder()
                ->update(Publication::class, 'p')
                ->set('p.archivePath', ':archivePath')
                ->set('p.archiveHash', ':hash')
                ->andWhere('p.id = :id')
                ->andWhere($hashCondition)
                ->setParameter('id', $publication->getId())
                ->setParameter('archivePath', $archivePath)
                ->setParameter('hash', $hash);
            if (null !== $previousHash) {
                $query->setParameter('previousHash', $previousHash);
            }
            $recorded = 1 === $query->getQuery()->execute();
        }

        $this->em->refresh($publication);

        if ($recorded) {
            $previousPath = $previous['archivePath'];
            if (null !== $previousPath && $previousPath !== $archivePath) {
                $this->bus->dispatch(new DeleteAsset($previousPath));
            }
        } elseif ($publication->getArchivePath() !== $archivePath) {
            $this->bus->dispatch(new DeleteAsset($archivePath));
        }

        return $recorded;
    }

    private function buildArchiveFile(Publication $publication, string $archivePath, LockInterface $lock): void
    {
        $workDir = sys_get_temp_dir().'/'.uniqid('publication-archive', true);
        $zipFile = $workDir.'.zip';
        mkdir($workDir, 0755, true);

        try {
            $usedNames = [];
            foreach ($publication->getAssets() as $asset) {
                $destination = $workDir.'/'.$this->getUniqueName(basename($asset->getOriginalName()), $usedNames);
                $this->copyStream($this->storageManager->getStream($asset->getPath()), $destination);
                $lock->refresh();
            }

            if ($publication->isIncludeDownloadTermsInArchive()
                && null !== $termsUrl = $publication->getDownloadTerms()->getUrl()) {
                $termsName = basename((string) parse_url($termsUrl, PHP_URL_PATH));
                $this->downloadTerms($termsUrl, $workDir.'/'.$this->getUniqueName($termsName ?: 'terms', $usedNames));
            }

            ZipUtil::zipDirectory($workDir, $zipFile);
            $lock->refresh();

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
