<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Http;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthStateEncoder
{
    public function encodeState(string $redirectUri, string $clientId = null, bool $internal = null): string
    {
        $params = [
            'r' => $redirectUri,
        ];

        if (null !== $clientId) {
            $params['c'] = $clientId;
        }
        if ($internal) {
            $params['d'] = '1';
        }

        return base64_encode(http_build_query($params));
    }

    public function decodeState(string $state): ?array
    {
        if (empty($state)) {
            throw new BadRequestHttpException('Empty state');
        }

        parse_str(base64_decode($state), $params);

        return [
            'internal' => $params['d'] ?? false,
            'redirect' => $params['r'],
            'clientId' => $params['c'] ?? null,
        ];
    }
}
