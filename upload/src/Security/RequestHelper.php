<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

abstract class RequestHelper
{
    public static function getAccessTokenFromRequest(Request $request, $authType = 'Bearer', bool $allowGET = true): ?string
    {
        if (
            (null !== $accessToken = $request->headers->get('Authorization'))
            && 0 === strpos($accessToken, $authType.' ')
        ) {
            return preg_replace('#^'.$authType.'\s+#', '', $accessToken);
        } elseif ($allowGET) {
            return $request->get('access_token');
        }

        return null;
    }
}
