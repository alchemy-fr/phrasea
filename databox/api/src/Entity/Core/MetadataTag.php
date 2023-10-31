<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 */
class MetadataTag
{
    /**
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=100, unique=true)
     */
    private string $id;

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $label = null;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $className = null;

    /*
     * return string|null
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string|null $label
     */
    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @param string|null $className
     */
    public function setClassName(?string $className): void
    {
        $this->className = $className;
    }

}
