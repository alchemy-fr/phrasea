<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\DTO\Input\TargetDataInput;
use App\Api\DTO\Input\TargetFormInput;
use App\Api\Processor\TargetDataProcessor;
use App\Api\Processor\TargetFormProcessor;
use App\Api\Provider\TargetDataContentProvider;
use App\Api\Provider\TargetFormProvider;
use App\DataProvider\TargetDataProvider;
use App\Form\Builder\FormDefinition;
use App\Form\Builder\InvalidFormDefinitionException;
use App\Security\Voter\FormDataEditorVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'target',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")'),
        new Patch(security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")'),
        new Post(security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")'),
        new Get(
            uriTemplate: '/targets/{id}/form',
            name: 'get_target_form',
            provider: TargetFormProvider::class,
        ),
        new Put(
            uriTemplate: '/targets/{id}/form',
            security: 'is_granted("'.FormDataEditorVoter::EDIT_FORM_SCHEMA.'")',
            throwOnNotFound: true,
            denormalizationContext: ['groups' => [TargetFormInput::GROUP]],
            input: TargetFormInput::class,
            name: 'put_target_form',
            processor: TargetFormProcessor::class,
        ),
        new Get(
            uriTemplate: '/targets/{id}/data',
            security: 'is_granted("'.FormDataEditorVoter::EDIT_TARGET_DATA.'")',
            name: 'get_target_data',
            provider: TargetDataContentProvider::class,
        ),
        new Put(
            uriTemplate: '/targets/{id}/data',
            security: 'is_granted("'.FormDataEditorVoter::EDIT_TARGET_DATA.'")',
            throwOnNotFound: true,
            denormalizationContext: ['groups' => [TargetDataInput::GROUP]],
            input: TargetDataInput::class,
            name: 'put_target_data',
            processor: TargetDataProcessor::class,
        ),
        new GetCollection(
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            provider: TargetDataProvider::class,
            parameters: [
                'query' => new QueryParameter(
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    schema: ['type' => 'string'],
                    description: 'Search query on the name',
                    castToArray: false,
                ),
            ],
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_INDEX],
    ],
    denormalizationContext: [
        'groups' => [self::GROUP_WRITE],
    ]
)]
#[ORM\Table]
#[ORM\Entity]
#[UniqueEntity(fields: ['slug'])]
class Target extends AbstractUuidEntity implements \Stringable
{
    final public const string GROUP_INDEX = 'target:i';
    final public const string GROUP_READ = 'target:r';
    public const string GROUP_WRITE = 'target:w';

    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    #[Assert\Regex('/^[a-z][a-z0-9_-]+/')]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: true)]
    protected ?string $slug = null;

    #[ORM\Column(type: Types::STRING, length: 1000)]
    #[Assert\Length(max: 1000)]
    #[Assert\NotBlank]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([self::GROUP_READ])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $hidden = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GROUP_INDEX, self::GROUP_WRITE])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Url]
    #[Groups([self::GROUP_WRITE])]
    private ?string $targetUrl = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups([self::GROUP_WRITE])]
    private ?string $defaultDestination = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups([self::GROUP_WRITE])]
    private ?string $authorizationScheme = null;

    #[ORM\Column(type: Types::STRING, length: 2000, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups([self::GROUP_WRITE])]
    private ?string $authorizationKey = null;

    /**
     * Null value allows everyone.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_WRITE])]
    private ?array $allowedGroups = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups([self::GROUP_INDEX])]
    private \DateTimeImmutable $createdAt;

    /**
     * The form of the uploads: a definition of the form builder (see FormDefinition).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $form = null;

    /**
     * Merged into the form data of every commit, over the values the user entered.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Groups([self::GROUP_INDEX])]
    public function getId(): string
    {
        return parent::getId();
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getAuthorizationKey(): ?string
    {
        return $this->authorizationKey;
    }

    public function setAuthorizationKey(?string $authorizationKey): void
    {
        $this->authorizationKey = $authorizationKey;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    public function getAllowedGroups(): ?array
    {
        return $this->allowedGroups;
    }

    public function setAllowedGroups(?array $allowedGroups): void
    {
        $this->allowedGroups = $allowedGroups;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDefaultDestination(): ?string
    {
        return $this->defaultDestination;
    }

    public function getPullModeUrl(): string
    {
        return sprintf('%s/commits?target=%s', getenv('UPLOADER_API_URL'), $this->getId());
    }

    public function setDefaultDestination(?string $defaultDestination): void
    {
        $this->defaultDestination = $defaultDestination;
    }

    public function __toString(): string
    {
        return (string) ($this->getName() ?? $this->getId());
    }

    public function getAuthorizationScheme(): ?string
    {
        return $this->authorizationScheme;
    }

    public function setAuthorizationScheme(?string $authorizationScheme): void
    {
        $this->authorizationScheme = $authorizationScheme;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getForm(): ?array
    {
        return $this->form;
    }

    public function setForm(?array $form): void
    {
        $this->form = $form;
    }

    /**
     * @throws InvalidFormDefinitionException
     */
    public function getFormDefinition(): ?FormDefinition
    {
        return null !== $this->form ? FormDefinition::fromArray($this->form) : null;
    }

    #[Groups([self::GROUP_INDEX])]
    public function getHasForm(): bool
    {
        return null !== $this->form;
    }

    #[Assert\Callback]
    public function validateForm(ExecutionContextInterface $context): void
    {
        if (null === $this->form) {
            return;
        }

        try {
            FormDefinition::fromArray($this->form);
        } catch (InvalidFormDefinitionException $e) {
            $context->buildViolation($e->getMessage())
                ->atPath('form'.($e->path ? '.'.$e->path : ''))
                ->addViolation();
        }
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * An empty object is no data.
     */
    public function setData(?array $data): void
    {
        $this->data = [] === $data ? null : $data;
    }

    #[Groups([self::GROUP_INDEX])]
    public function getHasData(): bool
    {
        return null !== $this->data;
    }
}
