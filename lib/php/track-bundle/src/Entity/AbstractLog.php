<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractLog extends AbstractUuidEntity
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $date;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $meta = [];

    public function __construct(?\DateTimeInterface $date = null)
    {
        parent::__construct();
        $this->date = $date ? \DateTimeImmutable::createFromInterface($date) : new \DateTimeImmutable();
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }
}
