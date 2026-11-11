<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\LayoutOptions;
use App\Model\MapOptions;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Configuration of a publication or a profile.
 */
#[ORM\Embeddable]
class PublicationConfig implements MergeableValueObjectInterface
{
    final public const string SECURITY_METHOD_PASSWORD = 'password';
    final public const string SECURITY_METHOD_AUTHENTICATION = 'authentication';
    private const string PASSWORD_OPTION = 'password';

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?bool $enabled = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?bool $downloadViaEmail = null;

    /**
     * Download Terms URL must also be set.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?bool $includeDownloadTermsInArchive = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?string $css = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    #[Assert\Length(max: 20)]
    private ?string $layout = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    #[Assert\Length(max: 30)]
    private ?string $theme = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?bool $publiclyListed = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?bool $downloadEnabled = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?\DateTimeImmutable $beginsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Embedded(class: TermsConfig::class)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    #[Assert\Valid]
    private TermsConfig $terms;

    #[ORM\Embedded(class: TermsConfig::class)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    #[Assert\Valid]
    private TermsConfig $downloadTerms;

    /**
     * "password" or "authentication".
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    #[Assert\Length(max: 20)]
    private ?string $securityMethod = null;

    /**
     * If securityMethod="password", the password is set with:
     * {"password":"$3cr3t!"}.
     * It is stored hashed and never output: a write without a password keeps
     * the current one.
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private array $securityOptions = [];

    /**
     * @var MapOptions|array|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private $mapOptions;

    /**
     * @var LayoutOptions|array|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private $layoutOptions;

    public function __construct()
    {
        $this->terms = new TermsConfig();
        $this->downloadTerms = new TermsConfig();
        $this->mapOptions = new MapOptions();
        $this->layoutOptions = new LayoutOptions();
    }

    public function mergeWith(MergeableValueObjectInterface $object): MergeableValueObjectInterface
    {
        $clone = clone $this;

        foreach ([
            'beginsAt',
            'css',
            'downloadTerms',
            'downloadEnabled',
            'downloadViaEmail',
            'enabled',
            'expiresAt',
            'layout',
            'publiclyListed',
            'securityMethod',
            'securityOptions',
            'mapOptions',
            'layoutOptions',
            'terms',
            'theme',
        ] as $property) {
            if ($clone->{$property} instanceof MergeableValueObjectInterface) {
                $clone->{$property} = $clone->{$property}->mergeWith($object->{$property});
            } else {
                $clone->{$property} = $object->{$property};
            }
        }

        return $clone;
    }

    public function getCss(): ?string
    {
        return $this->css;
    }

    public function setCss(?string $css): void
    {
        $this->css = $css;
    }

    public function getTerms(): TermsConfig
    {
        return $this->terms;
    }

    public function setTerms(TermsConfig $terms): void
    {
        $this->terms = $terms;
    }

    public function getDownloadTerms(): TermsConfig
    {
        return $this->downloadTerms;
    }

    public function setDownloadTerms(TermsConfig $downloadTerms): void
    {
        $this->downloadTerms = $downloadTerms;
    }

    public function getSecurityMethod(): ?string
    {
        return $this->securityMethod;
    }

    public function setSecurityMethod(?string $securityMethod): void
    {
        $this->securityMethod = $securityMethod;
        if (self::SECURITY_METHOD_PASSWORD !== $securityMethod) {
            unset($this->securityOptions[self::PASSWORD_OPTION]);
        }
    }

    /**
     * The security options, without the password hash.
     */
    public function getSecurityOptions(): array
    {
        $options = $this->securityOptions;
        unset($options[self::PASSWORD_OPTION]);

        return $options;
    }

    public function setSecurityOptions(array $securityOptions): void
    {
        $password = $securityOptions[self::PASSWORD_OPTION] ?? null;
        unset($securityOptions[self::PASSWORD_OPTION]);

        $hash = $this->securityOptions[self::PASSWORD_OPTION] ?? null;
        $this->securityOptions = $securityOptions;
        if (null !== $hash) {
            $this->securityOptions[self::PASSWORD_OPTION] = $hash;
        }

        if (is_string($password) && '' !== $password) {
            $this->securityOptions[self::PASSWORD_OPTION] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    /**
     * Whether these options carry anything, the password included.
     */
    public function hasSecurityOptions(): bool
    {
        return !empty($this->securityOptions);
    }

    /**
     * Read as "hasPassword": a "hasPassword()" method would be the accessor of
     * a "password" property for the serializer.
     */
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ])]
    public function getHasPassword(): bool
    {
        $hash = $this->securityOptions[self::PASSWORD_OPTION] ?? null;

        return is_string($hash) && '' !== $hash;
    }

    public function isPasswordValid(string $password): bool
    {
        $hash = $this->securityOptions[self::PASSWORD_OPTION] ?? null;

        return is_string($hash) && password_verify($password, $hash);
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(?string $layout): void
    {
        $this->layout = $layout;
    }

    public function getBeginsAt(): ?\DateTimeImmutable
    {
        return $this->beginsAt;
    }

    public function setBeginsAt(?\DateTimeImmutable $beginsAt): void
    {
        $this->beginsAt = $beginsAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): void
    {
        $this->theme = $theme;
    }

    /**
     * Sets a new password, hashed. An empty one keeps the current password.
     */
    public function setPassword(?string $password): void
    {
        if (null === $password || '' === $password) {
            return;
        }

        $this->setSecurityMethod(self::SECURITY_METHOD_PASSWORD);
        $this->securityOptions[self::PASSWORD_OPTION] = password_hash($password, PASSWORD_DEFAULT);
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isPubliclyListed(): ?bool
    {
        return $this->publiclyListed;
    }

    public function setPubliclyListed(?bool $publiclyListed): void
    {
        $this->publiclyListed = $publiclyListed;
    }

    public function getDownloadViaEmail(): ?bool
    {
        return $this->downloadViaEmail;
    }

    public function setDownloadViaEmail(?bool $downloadViaEmail): void
    {
        $this->downloadViaEmail = $downloadViaEmail;
    }

    public function getMapOptions(): MapOptions
    {
        if (null === $this->mapOptions || is_array($this->mapOptions)) {
            $this->mapOptions = new MapOptions($this->mapOptions);
        }

        return $this->mapOptions;
    }

    public function setMapOptions($mapOptions): void
    {
        $this->mapOptions = $mapOptions;
    }

    public function getLayoutOptions(): LayoutOptions
    {
        if (null === $this->layoutOptions || is_array($this->layoutOptions)) {
            $this->layoutOptions = new LayoutOptions($this->layoutOptions);
        }

        return $this->layoutOptions;
    }

    public function setLayoutOptions($layoutOptions): void
    {
        $this->layoutOptions = $layoutOptions;
    }

    public function getIncludeDownloadTermsInArchive(): ?bool
    {
        return $this->includeDownloadTermsInArchive;
    }

    public function setIncludeDownloadTermsInArchive(?bool $includeDownloadTermsInArchive): void
    {
        $this->includeDownloadTermsInArchive = $includeDownloadTermsInArchive;
    }

    public function getDownloadEnabled(): ?bool
    {
        return $this->downloadEnabled;
    }

    public function setDownloadEnabled(?bool $downloadEnabled): void
    {
        $this->downloadEnabled = $downloadEnabled;
    }
}
