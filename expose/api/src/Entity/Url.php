<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;

/**
 * Configuration of a publication or a profile.
 */
class Url implements \JsonSerializable, \Stringable
{
    public function __construct(
        /**
         * @ApiProperty()
         */
        private ?string $text = null,
        /**
         * @ApiProperty(writable=true)
         */
        private ?string $url = null
    ) {
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    public function serialize()
    {
        return json_encode([
            'text' => $this->text,
            'url' => $this->url,
        ], JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return [
            'text' => $this->text,
            'url' => $this->url,
        ];
    }

    public function unserialize($serialized)
    {
        $data = json_decode((string) $serialized, true, 512, JSON_THROW_ON_ERROR);

        $this->text = $data['text'] ?? null;
        $this->url = $data['url'] ?? null;
    }

    public function __toString(): string
    {
        return (string) $this->serialize();
    }

    public static function mapUrls(array $urls): array
    {
        return array_map(function ($url): self {
            if ($url instanceof self) {
                return $url;
            }

            return new self($url['text'] ?? null, $url['url'] ?? null);
        }, $urls);
    }
}
