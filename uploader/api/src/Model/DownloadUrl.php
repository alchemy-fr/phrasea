<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Api\DTO\Input\DownloadUrlInput;
use App\Api\Processor\DownloadUrlProcessor;
use App\Entity\Target;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'download',
    operations: [
        new Post(
            input: DownloadUrlInput::class,
            processor: DownloadUrlProcessor::class,
        ),
    ])]
class DownloadUrl
{
    private ?string $url = null;

    private ?array $data = null;
    private ?array $formData = null;

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

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    public function getFormData(): ?array
    {
        return $this->formData;
    }

    public function setFormData(?array $formData): void
    {
        $this->formData = $formData;
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
