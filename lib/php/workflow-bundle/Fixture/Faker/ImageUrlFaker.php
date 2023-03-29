<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Fixture\Faker;

class ImageUrlFaker extends AbstractCachedFaker
{
    public function imageUrlRandomRatio(
        string $workspaceId,
        string $lock,
        int $size = 1000,
        $theme = 'landscape'
    ): string {
        $ratios = [
            16 / 9,
            1,
            4 / 3,
            3 / 4,
            9 / 16,
        ];
        $ratio = $ratios[(int) $lock % count($ratios)];

        if ($ratio >= 1) {
            $width = $size;
            $height = $size / $ratio;
        } else {
            $width = $size * $ratio;
            $height = $size;
        }
        $width = round($width);
        $height = round($height);

        $baseUrl = 'https://loremflickr.com';

        $url = sprintf($baseUrl.'/%s/%s/%s?lock=%s',
            $width,
            $height,
            $theme,
            $lock
        );
        $extension = 'jpg';

        return $this->download(
            $workspaceId,
            sprintf('%s-%s-%s-%s', $theme, $lock, $width, $height),
            $extension,
            $url
        );
    }
}
