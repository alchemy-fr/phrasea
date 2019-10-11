<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AdminExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('file_size', [$this, 'formatSize']),
        ];
    }

    public function formatSize(?int $size): ?string
    {
        if (null === $size) {
            return null;
        }

        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
}
