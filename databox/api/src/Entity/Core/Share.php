<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Output\ShareAlternateUrlOutput;
use App\Api\Model\Output\ShareAttachmentOutput;
use App\Api\Model\Output\ShareTermsOutput;
use App\Api\Processor\ShareProcessor;
use App\Api\Provider\ShareAttachmentProvider;
use App\Api\Provider\ShareCollectionProvider;
use App\Api\Provider\ShareReadProvider;
use App\Api\Provider\ShareRenditionProvider;
use App\Entity\Traits\OwnerIdTrait;
use App\Listener\OwnerPersistableInterface;
use App\Repository\Core\ShareRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\String\ByteString;

#[ApiResource(
    shortName: 'share',
    operations: [
        new Get(
            uriTemplate: '/shares/{id}/public',
            normalizationContext: [
                'groups' => [
                    self::GROUP_PUBLIC_READ,
                ],
            ],
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
            provider: ShareReadProvider::class,
            name: 'share_public',
        ),
        new Get(
            uriTemplate: '/s/{id}/r/{rendition}',
            uriVariables: [
                'id' => 'id',
                'rendition' => 'rendition',
            ],
            normalizationContext: [
                'groups' => [
                    self::GROUP_PUBLIC_READ,
                ],
            ],
            name: 'share_public_rendition',
            provider: ShareRenditionProvider::class,
            extraProperties: [
                '_api_disable_swagger_provider' => true,
            ],
        ),
        new Get(
            uriTemplate: '/s/{id}/a/{attachment}',
            uriVariables: [
                'id' => 'id',
                'attachment' => 'attachment',
            ],
            normalizationContext: [
                'groups' => [
                    self::GROUP_PUBLIC_READ,
                ],
            ],
            name: 'share_public_attachment',
            provider: ShareAttachmentProvider::class,
            extraProperties: [
                '_api_disable_swagger_provider' => true,
            ],
        ),
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
            provider: ShareReadProvider::class,
        ),
        new Put(
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            provider: ShareReadProvider::class,
            processor: ShareProcessor::class,
        ),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)'
        ),
        new GetCollection(
            provider: ShareCollectionProvider::class,
        ),
        new Post(
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
            validate: false,
            provider: ShareReadProvider::class,
            processor: ShareProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
)]
#[ORM\Entity(repositoryClass: ShareRepository::class)]
class Share extends AbstractUuidEntity implements OwnerPersistableInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use OwnerIdTrait;
    final public const string GROUP_READ = 'share:read';
    final public const string GROUP_PUBLIC_READ = 'share:public';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([self::GROUP_READ])]
    private bool $enabled = true;

    /**
     * @var DoctrineCollection<int, Asset>
     */
    #[ORM\ManyToMany(targetEntity: Asset::class)]
    #[ORM\JoinTable(name: 'share_asset')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[Groups([self::GROUP_PUBLIC_READ, self::GROUP_READ])]
    private DoctrineCollection $assets;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    protected ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    protected ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false)]
    #[Groups([self::GROUP_READ])]
    private ?string $token = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $config = [];

    /**
     * @var ShareAlternateUrlOutput[]
     */
    #[Groups([self::GROUP_READ])]
    public array $alternateUrls = [];

    /**
     * @var ShareAttachmentOutput[]
     */
    #[Groups([self::GROUP_PUBLIC_READ, self::GROUP_READ])]
    public array $attachments = [];

    #[Groups([self::GROUP_PUBLIC_READ, self::GROUP_READ])]
    public ?ShareTermsOutput $terms = null;

    #[Groups([self::GROUP_PUBLIC_READ, self::GROUP_READ])]
    public ?string $logo = null;

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->token = ByteString::fromRandom(64)->toString();
        $this->assets = new ArrayCollection();
    }

    /**
     * @return DoctrineCollection<int, Asset>
     */
    public function getAssets(): DoctrineCollection
    {
        return $this->assets;
    }

    public function addAsset(Asset $asset): void
    {
        if (!$this->assets->contains($asset)) {
            $this->assets->add($asset);
        }
    }

    public function removeAsset(Asset $asset): void
    {
        $this->assets->removeElement($asset);
    }

    /**
     * @return Asset[]
     */
    public function getAssetsList(): array
    {
        return $this->assets->getValues();
    }

    public function getWorkspace(): ?Workspace
    {
        $first = $this->assets->first();

        return $first instanceof Asset ? $first->getWorkspace() : null;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
