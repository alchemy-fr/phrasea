<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Twig;

use Alchemy\AdminBundle\Utils\SizeUtils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AdminExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('file_size', [$this, 'formatSize']),
            new TwigFilter('substring', 'substr'),
        ];
    }

    public function formatSize(?int $sizeInBytes, bool $si = true): ?string
    {
        return SizeUtils::formatSize($sizeInBytes, $si);
    }
}
