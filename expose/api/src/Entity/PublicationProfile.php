<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
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
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(operations: [new Get(security: 'is_granted("READ", object)'), new Put(security: 'is_granted("EDIT", object)'), new Delete(security: 'is_granted("DELETE", object)'), new GetCollection(normalizationContext: ['groups' => ['profile:index'], 'swagger_definition_name' => 'List']), new Post(security: 'is_granted("profile:create")')], normalizationContext: ['groups' => ['profile:read'], 'swagger_definition_name' => 'Read'])]
#[ORM\Entity]
class PublicationProfile implements AclObjectInterface, \Stringable
{
    use CapabilitiesTrait;
    use ClientAnnotationsTrait;

    final public const GROUP_ADMIN_READ = 'profile:admin:read';
    final public const GROUP_READ = 'profile:read';
    final public const GROUP_LIST = 'profile:index';

    final public const API_READ = [
        'groups' => [self::GROUP_READ],
        'swagger_definition_name' => 'Read',
    ];
    final public const API_LIST = [
        'groups' => [self::GROUP_LIST],
        'swagger_definition_name' => 'List',
    ];

    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[Groups(['profile:index', 'profile:read', 'publication:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ApiProperty]
    #[ORM\Column(type: 'string', length: 150)]
    #[Groups(['profile:index', 'profile:read', 'publication:read'])]
    private ?string $name = null;

    #[ORM\Embedded(class: PublicationConfig::class)]
    #[Groups(['profile:index', 'profile:read', 'publication:read'])]
    private PublicationConfig $config;

    #[ApiProperty]
    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['profile:admin:read'])]
    private ?string $ownerId = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['profile:read'])]
    private \DateTime $createdAt;

    /**
     * @var Publication[]|Collection
     */
    #[ORM\OneToMany(targetEntity: Publication::class, mappedBy: 'profile')]
    private ?Collection $publications = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getCreatedAt(): \DateTime
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
