<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Repository\Core\TermsSignatureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records that a user has signed a specific version of a workspace's Terms & Conditions.
 * Signatures are never updated: signing newer terms creates a new row.
 */
#[ORM\Table(name: 'terms_signature')]
#[ORM\UniqueConstraint(name: 'uniq_terms_signature', columns: ['terms_version_id', 'user_id'])]
#[ORM\Entity(repositoryClass: TermsSignatureRepository::class)]
class TermsSignature extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: TermsVersion::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TermsVersion $termsVersion = null;

    #[ORM\Column(name: 'user_id', type: Types::STRING, length: 36, nullable: false)]
    private ?string $userId = null;

    public function getTermsVersion(): ?TermsVersion
    {
        return $this->termsVersion;
    }

    public function setTermsVersion(?TermsVersion $termsVersion): void
    {
        $this->termsVersion = $termsVersion;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }
}
