<?php

namespace Alchemy\ConfiguratorBundle\Entity;

use Alchemy\ConfiguratorBundle\Validator\ValidConfigurationEntryConstraint;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

#[ORM\Entity(repositoryClass: ConfiguratorEntryRepository::class)]
#[ORM\Index(columns: ['name'], name: 'configurator_entry_name_idx')]
#[ValidConfigurationEntryConstraint]
class ConfiguratorEntry
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[Groups(['_'])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ApiProperty(identifier: true)]
    private UuidInterface|string $id;

    #[ORM\Column(type: Types::STRING, length: 70, nullable: false)]
    #[NotBlank]
    #[NotNull]
    #[Assert\Length(min: 1, max: 70)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $value = '';

    public function __construct(string|UuidInterface|null $id = null)
    {
        if (null !== $id) {
            if ($id instanceof UuidInterface) {
                $this->id = $id;
            } else {
                $this->id = Uuid::fromString($id);
            }
        } else {
            $this->id = Uuid::uuid4();
        }
    }

    public function getId(): string
    {
        if (is_string($this->id)) {
            return $this->id;
        }

        return $this->id->toString();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->getId(),
        ];
    }

    public function __unserialize($data): void
    {
        $this->id = Uuid::fromString($data['id']);
    }
}
