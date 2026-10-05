<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Repository\Core\FileDuplicateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a file whose analysis detected duplicates to each duplicate file
 * (the source file of another asset). This table is the source of truth
 * for duplicates; the file analysis JSON payload does not store file IDs.
 */
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'file_duplicate_uniq', columns: ['file_id', 'duplicate_file_id', 'analyzer'])]
#[ORM\Index(name: 'file_duplicate_dup_idx', columns: ['duplicate_file_id'])]
#[ORM\Entity(repositoryClass: FileDuplicateRepository::class)]
class FileDuplicate extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?File $file = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?File $duplicateFile = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $analyzer = null;

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getDuplicateFile(): ?File
    {
        return $this->duplicateFile;
    }

    public function setDuplicateFile(?File $duplicateFile): void
    {
        $this->duplicateFile = $duplicateFile;
    }

    public function getAnalyzer(): ?string
    {
        return $this->analyzer;
    }

    public function setAnalyzer(?string $analyzer): void
    {
        $this->analyzer = $analyzer;
    }
}
