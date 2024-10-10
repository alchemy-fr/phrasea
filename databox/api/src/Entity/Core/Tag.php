<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\TagInput;
use App\Api\Model\Output\TagOutput;
use App\Api\Provider\TagCollectionProvider;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Repository\Core\TagRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Length;

#[ApiResource(
    shortName: 'tag',
    operations: [
        new Get(
            normalizationContext: ['groups' => [
                '_',
                Tag::GROUP_READ,
            ]],
        ),
        new GetCollection(),
        new Post(
            normalizationContext: ['groups' => [
                '_',
                Tag::GROUP_READ,
            ]],
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
        ),
        new Put(
            normalizationContext: ['groups' => [
                '_',
                Tag::GROUP_READ,
            ]],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
        ),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)',
        ),
    ],
    normalizationContext: ['groups' => [
        '_',
        Tag::GROUP_LIST,
    ]],
    input: TagInput::class,
    output: TagOutput::class,
    provider: TagCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'ws_name_uniq', columns: ['workspace_id', 'name'])]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: ['workspace'])]
class Tag extends AbstractUuidEntity implements TranslatableInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use LocaleTrait;
    use WorkspaceTrait;
    final public const GROUP_READ = 'tag:read';
    final public const GROUP_LIST = 'tag:index';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $translations = null;

    #[ORM\Column(type: Types::STRING, length: 6, nullable: true)]
    private ?string $color = null;

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->getName() ?? $this->getId();
    }

    public function getColor(): ?string
    {
        if ($this->color) {
            return '#'.$this->color;
        }

        return null;
    }

    public function setColor(?string $color): void
    {
        if ($color && '#' === $color[0]) {
            $color = substr($color, 1);
        }

        $this->color = $color;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations;
    }
}
