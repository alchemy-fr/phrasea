<?php

declare(strict_types=1);

namespace App\Entity\Template;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Output\AttributeOutput;
use App\Api\Processor\TemplateAttributeInputProcessor;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'template-attribute',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
    ],
    normalizationContext: [
        'groups' => [Attribute::GROUP_LIST],
    ],
    input: AttributeInput::class,
    output: AttributeOutput::class,
    processor: TemplateAttributeInputProcessor::class,
)]
#[ORM\Entity]
class TemplateAttribute extends AbstractBaseAttribute
{
    #[ORM\ManyToOne(targetEntity: AssetDataTemplate::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AssetDataTemplate $template = null;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([AssetDataTemplate::GROUP_READ])]
    protected ?AttributeDefinition $definition = null;

    /**
     * Unique ID to group translations of the same attribute.
     */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $translationId = null;

    /**
     * Unique ID to group translations of the same attribute.
     */
    #[ORM\ManyToOne(targetEntity: TemplateAttribute::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $translationOrigin = null;

    /**
     * Hashed value of the original translated string.
     */
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $translationOriginHash = null;

    #[ORM\OneToMany(targetEntity: TemplateAttribute::class, mappedBy: 'translationOrigin', cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?DoctrineCollection $translations = null;

    public function getDefinition(): ?AttributeDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?AttributeDefinition $definition): void
    {
        $this->definition = $definition;
    }

    public function getTranslationId(): ?string
    {
        return $this->translationId;
    }

    public function setTranslationId(?string $translationId): void
    {
        $this->translationId = $translationId;
    }

    public function getTranslationOrigin(): ?TemplateAttribute
    {
        return $this->translationOrigin;
    }

    public function getTranslationOriginHash(): ?string
    {
        return $this->translationOriginHash;
    }

    public function setTranslationOriginHash(?string $translationOriginHash): void
    {
        $this->translationOriginHash = $translationOriginHash;
    }

    public function getTemplate(): ?AssetDataTemplate
    {
        return $this->template;
    }

    public function setTemplate(?AssetDataTemplate $template): void
    {
        $this->template = $template;
    }
}
