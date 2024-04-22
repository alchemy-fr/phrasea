<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Twig;

use Alchemy\AdminBundle\Utils\SizeUtils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AdminExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('file_size', $this->formatSize(...)),
            new TwigFilter('substring', 'substr'),
            new TwigFilter('json_prettify', $this->jsonPrettify(...)),
        ];
    }

    public function jsonPrettify($value): string
    {
        $data = is_string($value) ?
            json_encode(json_decode($value, true, 512, JSON_THROW_ON_ERROR), JSON_PRETTY_PRINT)
            : json_encode($value, JSON_PRETTY_PRINT);

        return str_replace([
            '\\\\',
            '\\"',
            '\\n',
        ], [
            '\\',
            '"',
            "\n",
        ], $data);
    }

    public function formatSize(?int $sizeInBytes, bool $si = true): ?string
    {
        return SizeUtils::formatSize($sizeInBytes, $si);
    }
}
