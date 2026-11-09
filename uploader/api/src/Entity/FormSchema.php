<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Api\Provider\TargetFormSchemaProvider;
use App\Form\Builder\FormDefinition;
use App\Form\Builder\InvalidFormDefinitionException;
use App\Security\Voter\FormDataEditorVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'form-schema',
    operations: [
        new Get(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new Get(
            uriTemplate: '/targets/{id}/form-schema',
            uriVariables: [
                'id' => new Link(toProperty: 'target', fromClass: Target::class),
            ],
            // A target without schema answers null rather than 404
            throwOnNotFound: false,
            name: 'get_form_schema_by_locale',
            provider: TargetFormSchemaProvider::class,
        ),
        new Delete(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new Patch(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new Post(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
        new GetCollection(security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")'),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_INDEX],
    ],
    denormalizationContext: [
        'groups' => [self::GROUP_WRITE],
    ]
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_target_locale', columns: ['locale', 'target_id'])]
#[UniqueEntity(fields: ['target', 'locale'], message: 'Form schema for this target and locale already exists', ignoreNull: false)]
#[ORM\Entity(repositoryClass: FormSchemaRepository::class)]
class FormSchema extends AbstractUuidEntity implements AclObjectInterface
{
    final public const int LOCALE_MODE_NO_LOCALE = 0;
    final public const int LOCALE_MODE_USE_UA = 1;
    final public const int LOCALE_MODE_FORCED = 2;

    final public const string FORMAT_LIFORM = 'liform';
    final public const string FORMAT_BUILDER = 'builder';
    final public const array FORMATS = [self::FORMAT_LIFORM, self::FORMAT_BUILDER];

    public const string GROUP_INDEX = 'formschema:i';
    public const string GROUP_WRITE = 'formschema:w';

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private ?Target $target = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private ?string $locale = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private int $localeMode = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::FORMAT_LIFORM])]
    #[Assert\Choice(choices: self::FORMATS)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private string $format = self::FORMAT_LIFORM;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotBlank]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private array $data = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['targetparams:index'])]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(?string $id = null)
    {
        parent::__construct($id);
    }

    #[Groups([self::GROUP_INDEX])]
    public function getId(): string
    {
        return parent::getId();
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    public function isBuilder(): bool
    {
        return self::FORMAT_BUILDER === $this->format;
    }

    #[Assert\Callback]
    public function validateBuilderDefinition(ExecutionContextInterface $context): void
    {
        if (!$this->isBuilder()) {
            return;
        }

        try {
            FormDefinition::fromArray($this->data);
        } catch (InvalidFormDefinitionException $e) {
            $context->buildViolation($e->getMessage())
                ->atPath('data'.($e->path ? '.'.$e->path : ''))
                ->addViolation();
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getJsonData(): string
    {
        return \GuzzleHttp\json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function setJsonData(?string $jsonData): void
    {
        $jsonData ??= '{}';

        $this->data = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAclOwnerId(): string
    {
        return '';
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }

    public function getLocaleMode(): int
    {
        return $this->localeMode;
    }

    public function setLocaleMode(int $localeMode): void
    {
        $this->localeMode = $localeMode;
    }
}
