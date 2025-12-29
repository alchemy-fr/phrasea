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
        $urls = [
            'mp4' => [
                'https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/fixtures/video-classic.mp4',
                'https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/fixtures/video-tall.mp4',
            ],
            'avi' => [
                'https://jsoncompare.org/LearningContainer/SampleFiles/Video/AVI/Sample-AVI-Video-File-for-Testing.avi',
            ],
            'mp3' => [
                'https://download.samplelib.com/mp3/sample-3s.mp3',
                'https://download.samplelib.com/mp3/sample-9s.mp3',
                'https://download.samplelib.com/mp3/sample-12s.mp3',
            ],
            'pdf' => [
                'https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/fixtures/one-page.pdf',
                'https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/fixtures/two-page.pdf',
            ],
        ][$type];

        $n = (int) $n;

        $url = $urls[$n % count($urls)];

        return $this->download($workspaceId, md5($url), $type, $url);
    }

    public function mediaUrl(string $url, ?string $extension = null): string
    {
        return $this->download('media', md5($url), $extension ?? pathinfo($url, PATHINFO_EXTENSION), $url);
    }
}
