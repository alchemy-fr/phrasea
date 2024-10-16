<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\NullableWorkspaceTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_key', columns: ['workspace_id', 'name'])]
#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['workspace' => 'exact'])]
class WorkspaceSecret extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use NullableWorkspaceTrait;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Groups(['secret:index'])]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
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
