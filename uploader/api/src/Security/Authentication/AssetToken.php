<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class AssetToken extends AbstractToken
{
    public function __construct(private readonly ?string $accessToken)
    {
        parent::__construct();
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }
}
