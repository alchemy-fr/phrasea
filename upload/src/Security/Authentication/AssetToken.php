<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class AssetToken extends AbstractToken
{
    /**
     * @var string|null
     */
    private $accessToken;

    public function __construct(?string $accessToken)
    {
        parent::__construct();
        $this->accessToken = $accessToken;

        $this->setAuthenticated(!empty($accessToken));
    }

    public function getCredentials()
    {
        return '';
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }
}
