<?php

declare(strict_types=1);

namespace App\Entity\Template;

use App\Entity\AbstractUuidEntity;
use App\Entity\Core\AttributeDefinition;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class TemplateAttribute extends AbstractUuidEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Asset", inversedBy="attributes")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?AssetDataTemplate $template = null;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private ?string $locale = null;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $position = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\AttributeDefinition", inversedBy="attributes")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?AttributeDefinition $definition = null;

    /**
     * Unique ID to group translations of the same attribute.
     *
     * @ORM\Column(type="uuid", nullable=true)
     */
    private ?string $translationId = null;

    /**
     * Unique ID to group translations of the same attribute.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Template\TemplateAttribute", inversedBy="translations")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?self $translationOrigin = null;

    /**
     * Hashed value of the original translated string.
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private ?string $translationOriginHash = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Template\TemplateAttribute", mappedBy="translationOrigin", cascade={"remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $translations = null;

    /**
     * @ORM\Column(type="text", nullable=false)
     */
    private ?string $value = null;

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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function hasLocale(): bool
    {
        return null !== $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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
