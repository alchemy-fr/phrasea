<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Model\Input\TagInput;
use App\Api\Model\Output\TagOutput;
use App\Api\Processor\InputMapperProcessor;
use App\Api\Provider\TagCollectionProvider;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\SuggestQueryFilter;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\TranslationsTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Repository\Core\TagRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints\Length;

#[ApiResource(
    shortName: 'tag',
    operations: [
        new Get(
            normalizationContext: ['groups' => [
                '_',
                Tag::GROUP_READ,
            ]],
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new GetCollection(
            parameters: [
                'workspace' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'workspace',
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'workspaceId'],
                ),
                'workspace[]' => new QueryParameter(property: 'workspace', openApi: false),
                'query' => new QueryParameter(
                    filter: new SuggestQueryFilter(),
                    schema: ['type' => 'string'],
                    description: 'Search-as-you-type on the name (switches the search to Elasticsearch)',
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'name'],
                    castToArray: false,
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    description: 'Page size (max 50 on a "query" search)',
                    castToArray: false,
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    castToArray: false,
                ),
            ],
        ),
        new Post(
            normalizationContext: ['groups' => [
                '_',
                Tag::GROUP_READ,
            ]],
            extraProperties: [InputMapperProcessor::ENTITY_SECURITY => 'is_granted("'.AbstractVoter::CREATE.'", object)'],
            processor: InputMapperProcessor::class,
        ),
        new Patch(
            normalizationContext: ['groups' => [
                '_',
                Tag::GROUP_READ,
            ]],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            processor: InputMapperProcessor::class,
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
    order: [
        'name' => 'ASC',
    ],
    provider: TagCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'ws_name_uniq', columns: ['workspace_id', 'name'])]
#[UniqueEntity(
    fields: ['workspace', 'name'],
    errorPath: 'name',
)]
#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag extends AbstractUuidEntity implements TranslatableInterface, \Stringable, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use LocaleTrait;
    use WorkspaceTrait;
    use TranslationsTrait;
    final public const int OBJECT_INDEX = 19;

    final public const string GROUP_READ = 'tag:r';
    final public const string GROUP_LIST = 'tag:i';

    final public const string TR_FIELD_NAME = 'name';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Length(max: 100)]
    private string $name;

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
}
