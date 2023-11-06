<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\DownloadUrlAction;
use App\Entity\Target;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [new Post(controller: DownloadUrlAction::class)], shortName: 'download')]
class DownloadUrl
{
    private ?string $url = null;

    private array $data = [];

    #[Assert\NotNull]
    private ?Target $target = null;

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }
}
