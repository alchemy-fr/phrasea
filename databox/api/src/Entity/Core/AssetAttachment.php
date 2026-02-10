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
use App\Api\Model\Input\AssetAttachmentInput;
use App\Entity\Traits\ExtraMetadataTrait;
use App\Security\Voter\AbstractVoter;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'attachment',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new Post(
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Put(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new GetCollection(
            normalizationContext: [
                'groups' => [
                    AssetAttachment::GROUP_LIST,
                ],
            ],
        ),
    ],
    normalizationContext: [
        'groups' => [
            AssetAttachment::GROUP_LIST,
            AssetAttachment::GROUP_READ,
            Asset::GROUP_LIST,
            Asset::GROUP_READ,
        ],
    ],
    input: AssetAttachmentInput::class,
)]
#[ORM\Table]
#[ORM\Entity]
#[SameWorkspaceConstraint(
    properties: ['asset.workspace', 'attachment.workspace']
)]
class AssetAttachment extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use ExtraMetadataTrait;

    final public const string GROUP_READ = 'aat:read';
    final public const string GROUP_LIST = 'aat:index';

    #[Groups([AssetAttachment::GROUP_LIST, Asset::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([AssetAttachment::GROUP_LIST])]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups([AssetAttachment::GROUP_LIST, Asset::GROUP_READ])]
    private ?Asset $attachment = null;

    #[Groups([AssetAttachment::GROUP_LIST])]
    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private int $priority = 0;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getAttachment(): ?Asset
    {
        return $this->attachment;
    }

    public function setAttachment(?Asset $file): void
    {
        $this->attachment = $file;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }
}
