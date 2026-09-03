<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\TranslationsTrait;
use App\Repository\Core\TermsVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A frozen version of a workspace's Terms & Conditions.
 * A new row is created each time the terms content changes,
 * so signatures always reference the exact content that was signed.
 *
 * Content is either a text (translatable, rendered to PDF on demand)
 * or a PDF provided directly (a File of the workspace); the PDF takes
 * precedence.
 */
#[ORM\Table(name: 'terms_version')]
#[ORM\UniqueConstraint(name: 'uniq_terms_version', columns: ['workspace_id', 'version'])]
#[ORM\Entity(repositoryClass: TermsVersionRepository::class)]
class TermsVersion extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use TranslationsTrait;

    final public const string TR_FIELD_TEXT = 'text';

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Workspace $workspace = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $version = 1;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private ?string $text = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?File $file = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $checksum = null;

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function hasFile(): bool
    {
        return null !== $this->file;
    }

    public function isEmpty(): bool
    {
        return null === $this->file && '' === trim((string) $this->text);
    }
}
