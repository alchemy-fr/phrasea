<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

class MediaFaker extends AbstractCachedFaker
{
    private const string THEMED_BUCKET_URL = 'https://alchemy-phrasea-fixtures.s3.eu-west-3.amazonaws.com/fixtures/media';

    /**
     * @var array<string, true> URLs of the themed media that could not be fetched
     */
    private array $unavailableMedia = [];

    /**
     * @param string|null $set themed set of the fixtures bucket ("<workspace>/<kind>", e.g. "northbeam-press/podcast"),
     *                         where $n is the number of the file; a file missing from the set is replaced by a generic one
     */
    public function randomMedia(
        string $workspaceId,
        string $type,
        string $n,
        ?string $set = null,
    ): string {
        if (null !== $set) {
            $url = sprintf('%s/%s/%d.%s', self::THEMED_BUCKET_URL, $set, (int) $n, $type);
            if (!isset($this->unavailableMedia[$url])) {
                try {
                    return $this->downloadAndStore($workspaceId, md5($url), $type, $url);
                } catch (\InvalidArgumentException $e) {
                    $this->unavailableMedia[$url] = true;
                    $this->logger->warning(sprintf('Media set "%s" unavailable, falling back to a generic media: %s', $set, $e->getMessage()));
                }
            }
        }

        $bucketBaseUrl = 'https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/fixtures/';

        $urls = [
            'mp4' => [
                $bucketBaseUrl.'video-classic.mp4',
                $bucketBaseUrl.'video-tall.mp4',
            ],
            'avi' => [
                $bucketBaseUrl.'Sample-AVI-Video-File-for-Testing.avi',
            ],
            'mp3' => [
                $bucketBaseUrl.'soundreality-drums-loop-75bpm-455455.mp3',
                $bucketBaseUrl.'soundreality-drums-loop-75bpm-3-455453.mp3',
                $bucketBaseUrl.'soundreality-drums-loop-80bpm-455452.mp3',
            ],
            'pdf' => [
                $bucketBaseUrl.'one-page.pdf',
                $bucketBaseUrl.'two-page.pdf',
            ],
        ][$type];

        $n = (int) $n;

        $url = $urls[$n % count($urls)];

        return $this->downloadAndStore($workspaceId, md5($url), $type, $url);
    }

    public function mediaUrl(string $url, ?string $extension = null): string
    {
        return $this->downloadAndStore('media', md5($url), $extension ?? pathinfo($url, PATHINFO_EXTENSION), $url);
    }
}
