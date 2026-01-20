<?php

declare(strict_types=1);

namespace App\Entity;

use App\Model\LayoutOptions;
use App\Model\MapOptions;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Configuration of a publication or a profile.
 */
#[ORM\Embeddable]
class PublicationConfig implements MergeableValueObjectInterface
{
    final public const string SECURITY_METHOD_PASSWORD = 'password';
    final public const string SECURITY_METHOD_AUTHENTICATION = 'authentication';

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
    private ?bool $includeDownloadTermsInZippy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?string $css = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?string $layout = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
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
    private TermsConfig $terms;

    #[ORM\Embedded(class: TermsConfig::class)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private TermsConfig $downloadTerms;

    /**
     * "password" or "authentication".
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ, Publication::GROUP_WRITE, PublicationProfile::GROUP_WRITE])]
    private ?string $securityMethod = null;

    /**
     * If securityMethod="password", you must provide:
     * {"password":"$3cr3t!"}.
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
    }

    public function getSecurityOptions(): array
    {
        return $this->securityOptions;
    }

    public function setSecurityOptions(array $securityOptions): void
    {
        $this->securityOptions = $securityOptions;
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

    public function getPassword(): ?string
    {
        return $this->securityOptions['password'] ?? null;
    }

    public function setPassword(?string $password): void
    {
        if (!empty($password)) {
            $this->setSecurityMethod(self::SECURITY_METHOD_PASSWORD);
            $this->securityOptions['password'] = $password;
        } else {
            unset($this->securityOptions['password']);
        }
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

    public function getIncludeDownloadTermsInZippy(): ?bool
    {
        return $this->includeDownloadTermsInZippy;
    }

    public function setIncludeDownloadTermsInZippy(?bool $includeDownloadTermsInZippy): void
    {
        $this->includeDownloadTermsInZippy = $includeDownloadTermsInZippy;
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
