<?php

declare(strict_types=1);

namespace App\Entity\Parade;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use App\Parade\ParadeProvisioner;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'parade_tenant')]
#[ORM\UniqueConstraint(name: 'parade_tenant_slug_uniq', columns: ['slug'])]
#[ORM\Entity]
#[UniqueEntity(fields: ['slug'])]
class ParadeTenant extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Column(type: Types::STRING, length: 63, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9-]{0,62}$/', message: 'Use lowercase letters, digits and dashes only.')]
    #[Assert\Choice(choices: ParadeProvisioner::RESERVED_TENANT_SLUGS, match: false, message: 'This slug is reserved for the Parade groups.')]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $subRoles = false;

    #[ORM\Column(name: 'keycloak_groups', type: Types::JSON, nullable: false)]
    private array $groups = [];

    private bool $adoptExistingGroups = false;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function hasSubRoles(): bool
    {
        return $this->subRoles;
    }

    public function setSubRoles(bool $subRoles): void
    {
        $this->subRoles = $subRoles;
    }

    public function isAdoptExistingGroups(): bool
    {
        return $this->adoptExistingGroups;
    }

    public function setAdoptExistingGroups(bool $adoptExistingGroups): void
    {
        $this->adoptExistingGroups = $adoptExistingGroups;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->slug ?? '';
    }
}
