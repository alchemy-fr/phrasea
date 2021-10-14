<?php

declare(strict_types=1);

namespace App\Border\Model\Upload;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @ApiResource(
 *  shortName="incoming-upload",
 *  itemOperations={
 *      "get"
 *  },
 *  collectionOperations={
 *     "post"
 *  }
 * )
 */
final class IncomingUpload
{
    /**
     * @ApiProperty(identifier=true, writable=true)
     */
    public ?string $commit_id = null;
    public ?array $assets = null;
    public ?string $publisher = null;
    public ?string $token = null;
    public ?string $base_url = null;

    public function toArray(): array
    {
        return [
            'commit_id' => $this->commit_id,
            'assets' => $this->assets,
            'publisher' => $this->publisher,
            'token' => $this->token,
            'base_url' => $this->base_url,
        ];
    }

    public static function fromArray(array $data): self
    {
        $self = new self;
        $self->commit_id = $data['commit_id'] ?? null;
        $self->assets = $data['assets'] ?? null;
        $self->publisher = $data['publisher'] ?? null;
        $self->token = $data['token'] ?? null;
        $self->base_url = $data['base_url'] ?? null;

        return $self;
    }
}
