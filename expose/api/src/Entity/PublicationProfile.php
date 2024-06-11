<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Traits\CapabilitiesTrait;
use App\Entity\Traits\ClientAnnotationsTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiFilter(SearchFilter::class, properties={"title": "partial", "description": "partial"})
 * @ApiResource(
 *     normalizationContext=PublicationProfile::API_READ,
 *     itemOperations={
 *         "get"={
 *              "security"="is_granted('READ', object)"
 *          },
 *         "put"={
 *              "security"="is_granted('EDIT', object)"
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)"
 *         },
 *     },
 *     collectionOperations={
 *         "get"={
 *              "normalization_context"=PublicationProfile::API_LIST,
 *          },
 *         "post"={
 *             "security"="is_granted('profile:create')"
 *         }
 *     }
 * )
 */
class PublicationProfile implements AclObjectInterface
{
    use CapabilitiesTrait;
    use ClientAnnotationsTrait;

    const GROUP_ADMIN_READ = 'profile:admin:read';
    const GROUP_READ = 'profile:read';
    const GROUP_LIST = 'profile:index';

    const API_READ = [
        'groups' => [self::GROUP_READ],
        'swagger_definition_name' => 'Read',
    ];
    const API_LIST = [
        'groups' => [self::GROUP_LIST],
        'swagger_definition_name' => 'List',
    ];

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"profile:index", "profile:read", "publication:read"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="string", length=150)
     * @Groups({"profile:index", "profile:read", "publication:read"})
     */
    private ?string $name = null;

    /**
     * @ORM\Embedded(class="App\Entity\PublicationConfig")
     * @Groups({"profile:index", "profile:read", "publication:read"})
     */
    private PublicationConfig $config;

    /**
     * @ApiProperty()
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"profile:admin:read"})
     */
    private ?string $ownerId = null;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"profile:read"})
     */
    private DateTime $createdAt;

    /**
     * @var Publication[]|Collection
     * @ORM\OneToMany(targetEntity="App\Entity\Publication", mappedBy="profile")
     */
    private ?Collection $publications = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function __toString()
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
