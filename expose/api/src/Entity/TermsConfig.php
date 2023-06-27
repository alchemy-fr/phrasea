<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Embeddable]
class TermsConfig implements MergeableValueObjectInterface
{
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['profile:read', 'publication:read'])]
    private ?string $text = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['profile:read', 'publication:read'])]
    private ?string $url = null;

    public function mergeWith(MergeableValueObjectInterface $object): MergeableValueObjectInterface
    {
        $clone = clone $this;
        foreach ([
                     'text',
                     'url',
                 ] as $property) {
            if (null !== $object->{$property}) {
                if ($clone->{$property} instanceof MergeableValueObjectInterface) {
                    $clone->{$property}->mergeWith($object->{$property});
                } else {
                    $clone->{$property} = $object->{$property};
                }
            }
        }

        return $clone;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    #[Groups(['profile:read', 'publication:read'])]
    public function isEnabled(): bool
    {
        return null !== $this->text
            || null !== $this->url;
    }
}
