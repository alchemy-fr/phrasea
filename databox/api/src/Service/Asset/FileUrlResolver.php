<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Core\File;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class FileUrlResolver
{
    /**
     * Signed URLs are shared between requests for half of their validity,
     * so a URL served from the cache stays valid for at least ttl/2.
     */
    private const int CACHE_TTL_DIVIDER = 2;
    private const int MIN_CACHEABLE_TTL = 120;

    private readonly CacheInterface $requestCache;

    public function __construct(
        private readonly UrlSigner $urlSigner,
        #[Autowire(service: 'signed_url.cache')]
        private readonly AdapterInterface $signedUrlCache,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->requestCache = $cacheFactory->createCache();
    }

    public function resolveUrl(File $file): string
    {
        return match ($file->getStorage()) {
            File::STORAGE_S3_MAIN => $this->getSignedUrl($file->getPath(), $file->getType()),
            File::STORAGE_URL => $file->getPath(),
            default => throw new \RuntimeException(sprintf('Unsupported storage "%s"', $file->getStorage())),
        };
    }

    /**
     * Resolve the signed URLs of many files at once (one cache round trip)
     * so that the following resolveUrl() calls are served from memory.
     *
     * @param File[] $files
     */
    public function preloadUrls(array $files): void
    {
        $toSign = [];
        foreach ($files as $file) {
            if (File::STORAGE_S3_MAIN === $file->getStorage()) {
                $toSign[$this->getCacheKey($file->getPath(), $file->getType())] = $file;
            }
        }
        if (empty($toSign)) {
            return;
        }

        $cacheTtl = $this->getCacheTtl();
        if (null === $cacheTtl) {
            return;
        }

        $missing = [];
        foreach ($this->signedUrlCache->getItems(array_keys($toSign)) as $key => $item) {
            if ($item->isHit()) {
                $url = $item->get();
                $this->requestCache->get($key, fn (): string => $url);
            } else {
                $missing[$key] = $item;
            }
        }

        foreach ($missing as $key => $item) {
            $url = $this->sign($toSign[$key]->getPath(), $toSign[$key]->getType());
            $item->set($url);
            $item->expiresAfter($cacheTtl);
            $this->signedUrlCache->saveDeferred($item);
            $this->requestCache->get($key, fn (): string => $url);
        }
        if (!empty($missing)) {
            $this->signedUrlCache->commit();
        }
    }

    private function getSignedUrl(string $path, ?string $type): string
    {
        $cacheTtl = $this->getCacheTtl();
        if (null === $cacheTtl) {
            return $this->sign($path, $type);
        }

        $key = $this->getCacheKey($path, $type);

        return $this->requestCache->get(
            $key,
            fn (): string => $this->signedUrlCache->get($key, function (ItemInterface $item) use ($path, $type, $cacheTtl): string {
                $item->expiresAfter($cacheTtl);

                return $this->sign($path, $type);
            })
        );
    }

    /**
     * The response type comes from the known file type: the one stored on the
     * object was declared by the uploading client.
     */
    private function sign(string $path, ?string $type): string
    {
        return $this->urlSigner->getSignedUrl($path, [
            'type' => $type,
            'inline' => true,
        ]);
    }

    private function getCacheTtl(): ?int
    {
        $ttl = $this->urlSigner->getTtl();
        if ($ttl < self::MIN_CACHEABLE_TTL) {
            return null;
        }

        return intdiv($ttl, self::CACHE_TTL_DIVIDER);
    }

    private function getCacheKey(string $path, ?string $type): string
    {
        return hash('xxh128', $path."\0".$type);
    }
}
