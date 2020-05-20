<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Configuration of a publication or a profile.
 *
 * @ORM\Embeddable()
 */
class PublicationConfig implements MergeableValueObjectInterface
{
    const SECURITY_METHOD_NONE = null;
    const SECURITY_METHOD_PASSWORD = 'password';
    const SECURITY_METHOD_AUTHENTICATION = 'authentication';

    /**
     * @ApiProperty()
     * @ORM\Column(type="boolean")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?bool $enabled = null;

    /**
     * @ApiProperty()
     * @ORM\Column(type="boolean")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?bool $downloadViaEmail = null;

    /**
     * @ApiProperty()
     *
     * @var Url[]|array
     * @ORM\Column(type="json")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?array $urls = null;

    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?string $copyrightText = null;

    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?string $css = null;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?string $layout = null;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?string $theme = null;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?bool $publiclyListed = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?DateTime $beginsAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?DateTime $expiresAt = null;

    /**
     * @ApiProperty(readableLink=true)
     *
     * @ORM\Embedded(class="App\Entity\TermsConfig")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private TermsConfig $terms;

    /**
     * @ApiProperty(readableLink=true)
     *
     * @ORM\Embedded(class="App\Entity\TermsConfig")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private TermsConfig $downloadTerms;

    /**
     * "password" or "authentication".
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     *
     * @ApiProperty()
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?string $securityMethod = null;

    /**
     * If securityMethod="password", you must provide:
     * {"password":"$3cr3t!"}.
     *
     * @ORM\Column(type="json_array")
     *
     * @ApiProperty()
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?array $securityOptions = null;

    public function __construct()
    {
        $this->terms = new TermsConfig();
        $this->downloadTerms = new TermsConfig();
    }

    /**
     * applyDefaults and mergeWith methods are here to prevent
     * instantiating new Config from Symfony denormalization (from serializer component)
     * in PUT verb.
     */
    public function applyDefaults(): void
    {
        $this->enabled = false;
        $this->publiclyListed = false;
        $this->urls = [];
        $this->securityMethod = self::SECURITY_METHOD_NONE;
        $this->securityOptions = [];
        $this->downloadViaEmail = false;

        $this->terms->applyDefaults();
        $this->downloadTerms->applyDefaults();
    }

    public function mergeWith(MergeableValueObjectInterface $object): void
    {
        foreach ([
            'beginsAt',
            'copyrightText',
            'css',
            'downloadTerms',
            'downloadViaEmail',
            'enabled',
            'expiresAt',
            'layout',
            'publiclyListed',
            'securityMethod',
            'securityOptions',
            'terms',
            'theme',
            'urls',
                 ] as $property) {
            if (null !== $object->{$property}) {
                if ($this->{$property} instanceof MergeableValueObjectInterface) {
                    $this->{$property}->mergeWith($object->{$property});
                } else {
                    $this->{$property} = $object->{$property};
                }
            }
        }
    }

    public function getUrls(): array
    {
        return Url::mapUrls($this->urls);
    }

    public function setUrls(array $urls): void
    {
        $this->urls = Url::mapUrls($urls);
    }

    public function getCopyrightText(): ?string
    {
        return $this->copyrightText;
    }

    public function setCopyrightText(?string $copyrightText): void
    {
        $this->copyrightText = $copyrightText;
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
        $this->terms->mergeWith($terms);
    }

    public function getDownloadTerms(): TermsConfig
    {
        return $this->downloadTerms;
    }

    public function setDownloadTerms(TermsConfig $terms): void
    {
        $this->downloadTerms->mergeWith($terms);
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

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function getBeginsAt(): ?DateTime
    {
        return $this->beginsAt;
    }

    public function setBeginsAt(?DateTime $beginsAt): void
    {
        $this->beginsAt = $beginsAt;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTime $expiresAt): void
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
            if (self::SECURITY_METHOD_NONE === $this->securityMethod) {
                $this->setSecurityMethod(self::SECURITY_METHOD_PASSWORD);
            }
            $this->securityOptions['password'] = $password;
        } else {
            unset($this->securityOptions['password']);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isPubliclyListed(): bool
    {
        return $this->publiclyListed;
    }

    public function setPubliclyListed(bool $publiclyListed): void
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
}
