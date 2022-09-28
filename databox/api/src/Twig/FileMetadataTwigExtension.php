<?php

namespace App\Twig;

use App\Entity\Core\File;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FileMetadataTwigExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('file_metadata', [$this, 'fileMetadata']),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('file_metadata', [$this, 'fileMetadata']),
        ];
    }

    public function fileMetadata(?File $file, string $id): array
    {
        $ret = [
            'name' => null,
            'value' => null,
            'exists' => false,
        ];

        if ($file && ($meta = $file->getMetadata()) && array_key_exists($id, $meta)) {
            $v = $meta[$id]['value'];
            if (is_array($v)) {
                $v = join(' ; ', $v);
            }
            $ret = [
                'name' => $meta[$id]['name'],
                'value' => $v,
                'exists' => true,
            ];
        }

        return $ret;
    }
}
