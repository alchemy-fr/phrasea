<?php

declare(strict_types=1);

namespace App\Entity\AttributeList;

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
use App\Api\Model\Input\AddToAttributeListInput;
use App\Api\Model\Input\AttributeListInput;
use App\Api\Model\Input\RemoveFromAttributeListInput;
use App\Api\Model\Output\AttributeListOutput;
use App\Api\Processor\AddToAttributeListProcessor;
use App\Api\Processor\RemoveFromAttributeListProcessor;
use App\Controller\Core\AttributeListItemSortAction;
use App\Controller\Core\RenditionDefinitionSortAction;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\AttributeList\AttributeListRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'attribute-list',
    operations: [
        new GetCollection(security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")'),
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ]
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Post(
            uriTemplate: '/attribute-lists/{id}/sort',
            controller: AttributeListItemSortAction::class,
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
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
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
            securityPostValidation: 'is_granted("'.AbstractVoter::CREATE.'", object)'
        ),
        new Post(
            uriTemplate: '/attribute-lists/default/items',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            input: AddToAttributeListInput::class,
            name: 'add_to_default_attribute_list',
            processor: AddToAttributeListProcessor::class,
        ),
        new Post(
            uriTemplate: '/attribute-lists/{id}/items',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: AddToAttributeListInput::class,
            name: 'add_to_attribute_list',
            processor: AddToAttributeListProcessor::class,
        ),
        new Post(
            uriTemplate: '/attribute-lists/{id}/remove',
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: RemoveFromAttributeListInput::class,
            name: 'remove_from_attribute-list',
            processor: RemoveFromAttributeListProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    input: AttributeListInput::class,
    output: AttributeListOutput::class,
)]
#[ORM\Entity(repositoryClass: AttributeListRepository::class)]
class AttributeList extends AbstractUuidEntity implements WithOwnerIdInterface, AclObjectInterface
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const string GROUP_READ = 'attribute-list:read';
    final public const string GROUP_LIST = 'attribute-list:index';
    final public const string GROUP_WRITE = 'attribute-list:w';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $public = false;

    #[ORM\OneToMany(mappedBy: 'list', targetEntity: AttributeListItem::class)]
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
     * @return AttributeListItem[]|Collection
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
        return $this->getTitle() ?? 'AttributeList - '.$this->getId();
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
