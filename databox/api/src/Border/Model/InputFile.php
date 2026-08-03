<?php

declare(strict_types=1);

namespace App\Border\Model;

use Alchemy\StorageBundle\Util\FileUtil;

class InputFile
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $type,
        private readonly int $size,
        private readonly string $url,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getExtension(): string
    {
        return FileUtil::getExtensionFromPath($this->name) ?? '';
    }

    public function getExtensionWithDot(): string
    {
        $ext = $this->getExtension();
        if (!empty($ext)) {
            return '.'.$ext;
        }

        return '';
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
