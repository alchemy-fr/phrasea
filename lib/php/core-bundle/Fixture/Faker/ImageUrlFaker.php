<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

class ImageUrlFaker extends AbstractCachedFaker
{
    public function imageUrlRandomRatio(
        string $workspaceId,
        string $lock,
        int $size = 1000,
        bool $plusOne = false,
        ?string $theme = null,
    ): string {
        if ($size <= 0) {
            throw new \InvalidArgumentException(sprintf('Size must be greater than 0, got %d', $size));
        }

        if (!preg_match('#(\d+)$#', $lock, $matches)) {
            throw new \InvalidArgumentException(sprintf('Lock must end with a number, got "%s"', $lock));
        }

        $lockNumber = $matches[1];
        if ($plusOne) {
            ++$lockNumber;
        }

        $ratios = [
            16 / 9,
            1,
            4 / 3,
            3 / 4,
            9 / 16,
        ];
        $ratio = $ratios[$lockNumber % count($ratios)];

        if ($ratio >= 1) {
            $width = $size;
            $height = $size / $ratio;
        } else {
            $width = $size * $ratio;
            $height = $size;
        }

        return $this->image($width, $height, $lockNumber, $workspaceId, $theme);
    }

    public function image(
        float|int $width,
        float|int $height,
        string $lockNumber,
        string $pathPrefix,
        ?string $theme = null,
    ): string {
        $width = round($width);
        $height = round($height);

        $baseUrl = 'https://picsum.photos';

        $url = sprintf($baseUrl.'/seed/%s/%s/%s.jpg',
            $lockNumber,
            $width,
            $height,
        );
        $extension = 'jpg';

        return $this->download(
            $pathPrefix,
            sprintf('%s-%s-%s-%s', $theme ?? 'default', $lockNumber, $width, $height),
            $extension,
            $url
        );
    }
}
