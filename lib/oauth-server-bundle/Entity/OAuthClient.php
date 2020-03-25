<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use FOS\OAuthServerBundle\Entity\Client as BaseClient;

/**
 * @ORM\Entity
 */
class OAuthClient extends BaseClient
{
    /**
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=80, unique=true)
     */
    protected $id;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var array
     *
     * @ORM\Column(type="json_array")
     */
    private $allowedScopes = [];

    public function __construct()
    {
        parent::__construct();
        $this->setRandomId(substr($this->getRandomId(), 0, 6));
        $this->createdAt = new DateTime();
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getAllowedScopes(): array
    {
        return $this->allowedScopes;
    }

    public function hasAuthorization(string $authorization): bool
    {
        return in_array($authorization, $this->allowedScopes, true);
    }

    public function setAllowedScopes(array $allowedScopes): void
    {
        $this->allowedScopes = $allowedScopes;
    }
}
