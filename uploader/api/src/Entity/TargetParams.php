<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'target-params',
    operations: [
        new Get(security: 'is_granted("EDIT_TARGET_DATA")'),
        new Delete(security: 'is_granted("EDIT_TARGET_DATA")'),
        new Put(security: 'is_granted("EDIT_TARGET_DATA")'),
        new Post(security: 'is_granted("EDIT_TARGET_DATA")'),
        new GetCollection(security: 'is_granted("EDIT_TARGET_DATA")'),
    ],
    normalizationContext: [
        'groups' => ['targetparams:index'],
    ],
    denormalizationContext: [
        'groups' => ['targetparams:write'],
    ]
)]
#[ORM\Entity]
class TargetParams implements AclObjectInterface
{
    /**
     * @var Uuid
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['targetparams:index'])]
    protected $id;

    #[ORM\OneToOne(targetEntity: Target::class, inversedBy: 'targetParams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['targetparams:index', 'targetparams:write'])]
    #[Assert\NotNull]
    #[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact')]
    private ?Target $target = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['targetparams:index', 'targetparams:write'])]
    private array $data = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['targetparams:index'])]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getJsonData(): ?string
    {
        return \GuzzleHttp\json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function setJsonData(?string $jsonData): void
    {
        $jsonData ??= '{}';

        $this->data = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
    }

    public function getAclOwnerId(): string
    {
        return '';
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(Target $target): void
    {
        $this->target = $target;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
