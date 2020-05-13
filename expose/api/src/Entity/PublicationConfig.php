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
class PublicationConfig
{
    const SECURITY_METHOD_NONE = null;
    const SECURITY_METHOD_PASSWORD = 'password';
    const SECURITY_METHOD_AUTHENTICATION = 'authentication';

    /**
     * @ApiProperty()
     * @ORM\Column(type="boolean")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private bool $enabled = false;

    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="json")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private array $urls = [];

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
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="Asset")
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?Asset $cover = null;

    /**
     * @Groups({"publication:admin:read", "publication:index"})
     */
    private ?string $coverUrl = null;

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
    private bool $publiclyListed = false;

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
     * "password" or "authentication".
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     *
     * @ApiProperty()
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private ?string $securityMethod = self::SECURITY_METHOD_NONE;

    /**
     * If securityMethod="password", you must provide:
     * {"password":"$3cr3t!"}.
     *
     * @ORM\Column(type="json_array")
     *
     * @ApiProperty()
     * @Groups({"profile:read", "publication:admin:read"})
     */
    private array $securityOptions = [];

    public function __construct()
    {
        $this->terms = new TermsConfig();
    }

    public function getUrls(): array
    {
        return $this->urls;
    }

    public function setUrls(array $urls): void
    {
        $this->urls = $urls;
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

    public function getCover(): ?Asset
    {
        return $this->cover;
    }

    public function setCover(?Asset $cover): void
    {
        $this->cover = $cover;
    }

    public function getTerms(): TermsConfig
    {
        return $this->terms;
    }

    public function setTerms(TermsConfig $terms): void
    {
        $this->terms = $terms;
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

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
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
}
