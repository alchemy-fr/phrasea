<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class AssetToken extends AbstractToken
{
    public function __construct(private readonly ?string $accessToken)
    {
        parent::__construct();

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
