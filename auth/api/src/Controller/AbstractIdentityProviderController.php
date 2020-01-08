<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractIdentityProviderController extends AbstractController
{
    protected function getRedirectParams(
        string $provider,
        string $redirectUri,
        string $clientId
    ): array {
        return [
            'provider' => $provider,
            'r' => $redirectUri,
            'cid' => $clientId,
        ];
    }
}
