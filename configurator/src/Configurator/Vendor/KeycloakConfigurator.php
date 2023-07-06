<?php

declare(strict_types=1);

namespace App\Configurator\Vendor;

use App\Util\UriTemplate;
use GuzzleHttp\RequestOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakConfigurator
{
    private ?string $token = null;

    public function __construct(
        private readonly HttpClientInterface $client,
    )
    {
    }

    public function __invoke()
    {
        $this->client->request('POST', '/{realm}/clients', [
            'json' => [

            ]
        ])
    }

    private function getToken(): string
    {
        if (null !== $this->token) {
            return $this->token;
        }

        ACCESS_TOKEN=$(curl -s $KEYCLOAK_URL/realms/$KEYCLOAK_REALM/protocol/openid-connect/token \
    -d client_id=$KEYCLOAK_CLIENT \
    -d grant_type=password \
    -d username=$KEYCLOAK_USER \
    -d password=$KEYCLOAK_PASSWORD\
    | jq -r '.access_token')

        $response = $this->client->request('POST', UriTemplate::resolve('/{realm}/clients', [
            'realm' => $this->realm,
        ]), [
            RequestOptions::FORM_PARAMS => [
                'client_id' => '',
                'grant_type' => '',
                'username' => '',
                'password' => '',
            ]
        ]);
    }
}
