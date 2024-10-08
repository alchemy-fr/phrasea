<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Core\ShareRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'workspace',
    operations: [
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)'
        ),
        new Put(
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)'
        ),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)'
        ),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
)]
#[ORM\Entity(repositoryClass: ShareRepository::class)]
class Share extends AbstractUuidEntity implements WithOwnerIdInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use OwnerIdTrait;
    final public const string GROUP_READ = 'share:read';

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    protected ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $token = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $config = [];

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
