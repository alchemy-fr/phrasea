<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

class MediaFaker extends AbstractCachedFaker
{
    public function randomMedia(
        string $workspaceId,
        string $type,
        string $n
    ): string
    {
        $urls = [
            'mp4' => [
                'https://www.learningcontainer.com/download/sample-mp4-video-file-download-for-testing/?wpdmdl=2727&refresh=6282e5734c9491652745587',
                'https://download.samplelib.com/mp4/sample-5s.mp4',
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
                'http://www.africau.edu/images/default/sample.pdf',
                'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'https://file-examples.com/storage/feddb42d8762894ad9bbbb0/2017/10/file-sample_150kB.pdf',
                'https://file-examples.com/storage/feddb42d8762894ad9bbbb0/2017/10/file-example_PDF_500_kB.pdf',
                'https://file-examples.com/storage/feddb42d8762894ad9bbbb0/2017/10/file-example_PDF_1MB.pdf',
            ]
        ][$type];

        $n = (int) $n;

        $url = $urls[$n % count($urls)];

        return $this->download($workspaceId, md5($url), $type, $url);
    }
}
