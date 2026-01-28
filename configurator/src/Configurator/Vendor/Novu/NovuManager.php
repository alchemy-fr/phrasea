<?php

namespace App\Configurator\Vendor\Novu;

use App\Util\HttpClientUtil;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class NovuManager
{
    public function __construct(
        private HttpClientInterface $novuClient,
    ) {
    }

    public function createAccount(
        string $email,
        string $password,
        string $firstName,
        string $organizationName,
    ): bool {
        return HttpClientUtil::debugError(function () use (
            $email,
            $password,
            $firstName,
            $organizationName,
        ): bool {
            try {
                $this->novuClient->request('POST', '/v1/auth/register', [
                    'json' => [
                        'email' => $email,
                        'password' => $password,
                        'firstName' => $firstName,
                        'organizationName' => $organizationName,
                    ],
                ]);

                return true;
            } catch (ClientExceptionInterface $exception) {
                if (400 === $exception->getCode()) {
                    $data = $exception->getResponse()->toArray(false);
                    if ('User already exists' === $data['message']) {
                        return false;
                    }
                }

                throw $exception;
            }
        });
    }

    public function getToken(
        string $email,
        string $password,
    ): string {
        $data = $this->novuClient->request('POST', '/v1/auth/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ])->toArray();

        return $data['data']['token'];
    }

    public function updateEnvironment(string $token): void
    {
        $data = $this->novuClient->request('GET', '/v1/environments', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ])->toArray();
        $envId = array_find($data['data'], fn (array $env): bool => 'prod' === $env['type'])['_id'];

        HttpClientUtil::debugError(function () use ($token, $envId): void {
            $this->novuClient->request('PUT', '/v1/environments/'.$envId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                'json' => [
                    'identifier' => getenv('NOVU_APPLICATION_IDENTIFIER'),
                ],
            ]);
        });

        //        HttpClientUtil::debugError(function () use ($token, $envId): void {
        //            $this->novuClient->request('POST', '/v1/environments/api-keys/update', [
        //                'headers' => [
        //                    'Authorization' => 'Bearer '.$token,
        //                    'novu-environment-id' => $envId,
        //                ],
        //                'json' => [
        //                    'apiKey' => getenv('NOVU_SECRET_KEY'),
        //                ],
        //            ])->toArray();
        //        });
        //
        //        $this->novuClient->request('GET', '/v1/environments', [
        //            'headers' => [
        //                'Authorization' => 'Bearer '.$token,
        //            ],
        //        ])->toArray();

        $data = $this->novuClient->request('GET', '/v1/environments', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ])->toArray();

        $environment = array_find($data['data'], fn (array $env): bool => $envId === $env['_id']);
        $apiKey = $environment['apiKeys'][0]['key'] ?? throw new \RuntimeException('API key not found');

        file_put_contents('/output', "NOVU_SECRET_KEY='{$apiKey}'\n", FILE_APPEND);
    }
}
