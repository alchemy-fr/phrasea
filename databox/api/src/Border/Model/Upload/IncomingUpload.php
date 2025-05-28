<?php

declare(strict_types=1);

namespace App\Border\Model\Upload;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\IncomingUploadProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'incoming-upload',
    operations: [
        new Post(processor: IncomingUploadProcessor::class),
    ],
)]
final class IncomingUpload
{
    #[ApiProperty(writable: true, identifier: true)]
    #[Assert\NotBlank]
    public ?string $commit_id = null;

    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    public ?array $assets = null;

    #[Assert\NotBlank]
    public ?string $publisher = null;

    #[Assert\NotBlank]
    public ?string $token = null;

    #[Assert\NotBlank]
    public ?string $base_url = null;

    public function toArray(): array
    {
        return [
            'commit_id' => $this->commit_id,
            'publisher' => $this->publisher,
            'token' => $this->token,
            'base_url' => $this->base_url,
        ];
    }

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->commit_id = $data['commit_id'] ?? null;
        $self->publisher = $data['publisher'] ?? null;
        $self->token = $data['token'] ?? null;
        $self->base_url = $data['base_url'] ?? null;

        return $self;
    }
}
