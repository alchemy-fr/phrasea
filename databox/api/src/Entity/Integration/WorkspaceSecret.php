<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiFilter(SearchFilter::class, properties={"workspace"="exact"})
 */
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_key', columns: ['workspace_id', 'name'])]
#[ORM\Entity(repositoryClass: \App\Repository\Core\AssetRepository::class)]
class WorkspaceSecret extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    #[Groups(['secret:index'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $value = null;

    private ?string $plainValue = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getPlainValue(): ?string
    {
        return $this->plainValue;
    }

    public function setPlainValue(?string $plainValue): void
    {
        $this->plainValue = $plainValue;
    }
}
