<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'attribute-entity',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)'
        ),
    ],
    normalizationContext: [
        'groups' => [
            self::GROUP_LIST,
        ],
    ],
)]

#[ORM\Entity(repositoryClass: AttributeEntityRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['type' => 'exact'])]
#[ORM\Index(columns: ['type'], name: 'attr_entity_type_idx')]
class AttributeEntity extends AbstractUuidEntity
{
    const TYPE_LENGTH = 100;

    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    final public const GROUP_READ = 'attr-entity:read';
    final public const GROUP_LIST = 'attr-entity:index';

    #[ORM\Column(type: Types::STRING, length: self::TYPE_LENGTH, nullable: false)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    private ?string $value = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    private ?string $locale = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $position = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    private ?array $translations = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations;
    }
}
