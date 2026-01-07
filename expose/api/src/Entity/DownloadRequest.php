<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use App\Security\Voter\DownloadRequestVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(security: 'is_granted("'.DownloadRequestVoter::LIST.'")'),
    ]
)]
#[ORM\Entity]
class DownloadRequest
{
    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[Groups([Publication::GROUP_INDEX, Publication::GROUP_READ, Asset::GROUP_READ])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    protected ?string $locale = null;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Publication $publication = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: SubDefinition::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?SubDefinition $subDefinition = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLocale(): string
    {
        return $this->locale ?? 'en';
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getSubDefinition(): ?SubDefinition
    {
        return $this->subDefinition;
    }

    public function setSubDefinition(?SubDefinition $subDefinition): void
    {
        $this->subDefinition = $subDefinition;
    }
}
