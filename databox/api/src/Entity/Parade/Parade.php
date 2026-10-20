<?php

declare(strict_types=1);

namespace App\Entity\Parade;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'parade')]
#[ORM\Entity]
class Parade extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const array DEFAULT_SETTINGS = [
        'rootGroupName' => 'parade',
        'superAdminsGroupName' => 'super-admins',
        'adminsGroupName' => 'admins',
        'technicalGroupName' => 'techniques',
        'functionalGroupName' => 'fonctionnels',
        'ownerAttribute' => 'phraseaApp',
        'ownerValue' => 'parade',
        'mainClientId' => 'phrasea-front',
        'serviceAccountClientId' => 'parade-admin',
        'serviceAccountRealmRole' => 'admin-scopes',
        'serviceAccountScopes' => 'workspace:list, collection:list, rendition-definition:list, attribute-definition:list, groups',
        'serviceAccountClientRoles' => 'view-users',
        'orderClientId' => 'parade-to-order',
        'loanClientId' => 'parade-to-loan',
    ];

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = 'Parade';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url(requireTld: false)]
    #[Assert\Length(max: 255)]
    private ?string $url = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $enabled = false;

    #[ORM\Column(name: 'keycloak_groups', type: Types::JSON, nullable: false)]
    private array $groups = [];

    #[ORM\Column(type: Types::JSON, nullable: false, options: ['default' => '{}'])]
    private array $settings = [];

    #[ORM\Column(type: Types::JSON, nullable: false, options: ['default' => '{}'])]
    private array $clientsReport = [];

    private bool $adoptExistingGroups = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isAdoptExistingGroups(): bool
    {
        return $this->adoptExistingGroups;
    }

    public function setAdoptExistingGroups(bool $adoptExistingGroups): void
    {
        $this->adoptExistingGroups = $adoptExistingGroups;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    public function getSetting(string $key): string
    {
        $value = $this->settings[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : self::DEFAULT_SETTINGS[$key];
    }

    public function getSettingList(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->getSetting($key))), fn (string $value): bool => '' !== $value));
    }

    private function setSetting(string $key, ?string $value): void
    {
        $value = null !== $value ? trim($value) : '';
        if ('' === $value || self::DEFAULT_SETTINGS[$key] === $value) {
            unset($this->settings[$key]);
        } else {
            $this->settings[$key] = $value;
        }
    }

    public function getClientsReport(): array
    {
        return $this->clientsReport;
    }

    public function setClientsReport(array $clientsReport): void
    {
        $this->clientsReport = $clientsReport;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getRootGroupName(): string
    {
        return $this->getSetting('rootGroupName');
    }

    public function setRootGroupName(?string $value): void
    {
        $this->setSetting('rootGroupName', $value);
    }

    public function getSuperAdminsGroupName(): string
    {
        return $this->getSetting('superAdminsGroupName');
    }

    public function setSuperAdminsGroupName(?string $value): void
    {
        $this->setSetting('superAdminsGroupName', $value);
    }

    public function getAdminsGroupName(): string
    {
        return $this->getSetting('adminsGroupName');
    }

    public function setAdminsGroupName(?string $value): void
    {
        $this->setSetting('adminsGroupName', $value);
    }

    public function getTechnicalGroupName(): string
    {
        return $this->getSetting('technicalGroupName');
    }

    public function setTechnicalGroupName(?string $value): void
    {
        $this->setSetting('technicalGroupName', $value);
    }

    public function getFunctionalGroupName(): string
    {
        return $this->getSetting('functionalGroupName');
    }

    public function setFunctionalGroupName(?string $value): void
    {
        $this->setSetting('functionalGroupName', $value);
    }

    public function getOwnerAttribute(): string
    {
        return $this->getSetting('ownerAttribute');
    }

    public function setOwnerAttribute(?string $value): void
    {
        $this->setSetting('ownerAttribute', $value);
    }

    public function getOwnerValue(): string
    {
        return $this->getSetting('ownerValue');
    }

    public function setOwnerValue(?string $value): void
    {
        $this->setSetting('ownerValue', $value);
    }

    public function getMainClientId(): string
    {
        return $this->getSetting('mainClientId');
    }

    public function setMainClientId(?string $value): void
    {
        $this->setSetting('mainClientId', $value);
    }

    public function getServiceAccountClientId(): string
    {
        return $this->getSetting('serviceAccountClientId');
    }

    public function setServiceAccountClientId(?string $value): void
    {
        $this->setSetting('serviceAccountClientId', $value);
    }

    public function getServiceAccountRealmRole(): string
    {
        return $this->getSetting('serviceAccountRealmRole');
    }

    public function setServiceAccountRealmRole(?string $value): void
    {
        $this->setSetting('serviceAccountRealmRole', $value);
    }

    public function getServiceAccountScopes(): string
    {
        return $this->getSetting('serviceAccountScopes');
    }

    public function setServiceAccountScopes(?string $value): void
    {
        $this->setSetting('serviceAccountScopes', $value);
    }

    public function getServiceAccountClientRoles(): string
    {
        return $this->getSetting('serviceAccountClientRoles');
    }

    public function setServiceAccountClientRoles(?string $value): void
    {
        $this->setSetting('serviceAccountClientRoles', $value);
    }

    public function getOrderClientId(): string
    {
        return $this->getSetting('orderClientId');
    }

    public function setOrderClientId(?string $value): void
    {
        $this->setSetting('orderClientId', $value);
    }

    public function getLoanClientId(): string
    {
        return $this->getSetting('loanClientId');
    }

    public function setLoanClientId(?string $value): void
    {
        $this->setSetting('loanClientId', $value);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
