<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AddToProfileInput;
use App\Api\Model\Input\ProfileInput;
use App\Api\Model\Input\RemoveFromProfileInput;
use App\Api\Model\Output\ProfileOutput;
use App\Api\Processor\AddToProfileProcessor;
use App\Api\Processor\RemoveFromProfileProcessor;
use App\Api\Provider\ProfileCollectionProvider;
use App\Controller\Core\ProfileItemSortAction;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Profile\ProfileRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'profile',
    operations: [
        new GetCollection(),
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ]
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Post(
            uriTemplate: '/profiles/{id}/sort',
            controller: ProfileItemSortAction::class,
            openapiContext: [
                'summary' => 'Reorder items',
                'description' => 'Reorder items',
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'description' => 'Ordered list of IDs',
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            input: false,
            output: false,
            read: false,
            name: 'attr_list_item_post_sort',
            provider: null
        ),
        new Put(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
        ),
        new Post(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            securityPostValidation: 'is_granted("'.AbstractVoter::CREATE.'", object)'
        ),
        new Post(
            uriTemplate: '/profiles/default/items',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            input: AddToProfileInput::class,
            name: 'add_to_default_profile',
            processor: AddToProfileProcessor::class,
        ),
        new Post(
            uriTemplate: '/profiles/{id}/items',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: AddToProfileInput::class,
            name: 'add_to_profile',
            processor: AddToProfileProcessor::class,
        ),
        new Post(
            uriTemplate: '/profiles/{id}/remove',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: RemoveFromProfileInput::class,
            name: 'remove_from_profile',
            processor: RemoveFromProfileProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    input: ProfileInput::class,
    output: ProfileOutput::class,
    provider: ProfileCollectionProvider::class,
)]
#[ORM\Entity(repositoryClass: ProfileRepository::class)]
class Profile extends AbstractUuidEntity implements WithOwnerIdInterface, AclObjectInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const int OBJECT_INDEX = 7;
    final public const string OBJECT_TYPE = 'profile';
    final public const string GROUP_READ = 'profile:read';
    final public const string GROUP_LIST = 'profile:index';
    final public const string GROUP_WRITE = 'profile:w';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $public = false;

    #[ORM\OneToMany(mappedBy: 'list', targetEntity: ProfileItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Collection $items = null;

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->items = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return ProfileItem[]|Collection
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? 'Profile - '.$this->getId();
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
