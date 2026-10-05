<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

class MediaFaker extends AbstractCachedFaker
{
    public function randomMedia(
        string $workspaceId,
        string $type,
        string $n,
    ): string {
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
