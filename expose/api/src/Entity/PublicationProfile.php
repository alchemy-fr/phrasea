<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\CapabilitiesTrait;
use App\Entity\Traits\ClientAnnotationsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(
            normalizationContext: [
                'groups' => [self::GROUP_LIST],
            ],
        ),
        new Post(security: 'is_granted("profile:create")'),
    ],
    normalizationContext: [
        'groups' => [
            self::GROUP_READ,
        ],
    ]
)]
#[ORM\Entity]
#[ApiFilter(OrderFilter::class, properties: [
    'name' => 'ASC',
])]
class PublicationProfile implements AclObjectInterface, \Stringable
{
    use CapabilitiesTrait;
    use ClientAnnotationsTrait;

    final public const GROUP_ADMIN_READ = 'profile:admin:read';
    final public const GROUP_READ = 'profile:read';
    final public const GROUP_LIST = 'profile:index';

    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, Publication::GROUP_READ])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ApiProperty]
    #[ORM\Column(type: 'string', length: 150)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, Publication::GROUP_READ])]
    private ?string $name = null;

    #[ORM\Embedded(class: PublicationConfig::class)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, Publication::GROUP_READ])]
    private PublicationConfig $config;

    #[ApiProperty]
    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups([self::GROUP_ADMIN_READ])]
    private ?string $ownerId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups([self::GROUP_READ])]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Publication[]|Collection
     */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Publication::class)]
    private ?Collection $publications = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->config = new PublicationConfig();
        $this->id = Uuid::uuid4();
        $this->publications = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getConfig(): PublicationConfig
    {
        return $this->config;
    }

    public function setConfig(PublicationConfig $config): void
    {
        $this->config = $this->config->mergeWith($config);
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->getName() ?? $this->getId();
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    /**
     * @return Publication[]
     */
    public function getPublications(): ?Collection
    {
        return $this->publications;
    }
}
