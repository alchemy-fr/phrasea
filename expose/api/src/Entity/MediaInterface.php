<?php

declare(strict_types=1);

namespace App\Entity;

interface MediaInterface
{
    public function getPath(): string;

    public function getMimeType(): string;
}
