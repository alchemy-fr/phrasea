<?php

declare(strict_types=1);

namespace App\Parade;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use App\Entity\Parade\ParadeLoginClient;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ParadeLoginClientApplier
{
    final public const string ATTRIBUTE_PREFIX = 'paradeLogin';

    public function __construct(
        private KeycloakClient $keycloakClient,
        private ServiceAccountClient $serviceAccountClient,
        private EntityManagerInterface $em,
        private ParadeProvisioner $provisioner,
    ) {
    }

    public function apply(string $page, string $baseClientId, array $attributes, string $paradeUrl): ParadeLoginClient
    {
        $root = ParadeLoginClient::ROOT_PAGE === $page;
        $clientId = $root ? $baseClientId : sprintf('%s--%s', $baseClientId, $page);
        $record = $this->em->getRepository(ParadeLoginClient::class)->findOneBy(['page' => $page])
            ?? new ParadeLoginClient($page, $clientId);
        $record->setClientId($clientId);
        $record->setAttributes($attributes);

        try {
            $this->serviceAccountClient->executeWithAccessToken(function (string $accessToken) use ($root, $baseClientId, $clientId, $attributes, $paradeUrl): void {
                $base = $this->keycloakClient->findClientByClientId($accessToken, $baseClientId)
                    ?? throw new \RuntimeException(sprintf('The Parade Keycloak client "%s" does not exist.', $baseClientId));

                if ($root) {
                    $this->keycloakClient->updateClient($accessToken, $base['id'], [
                        'clientId' => $baseClientId,
                        'attributes' => (object) $this->mergeAttributes($base['attributes'] ?? [], $attributes),
                    ]);

                    return;
                }

                $expected = $this->expectedClient($clientId, $baseClientId, $attributes, $paradeUrl);
                $existing = $this->keycloakClient->findClientByClientId($accessToken, $clientId);
                if (null === $existing) {
                    $this->keycloakClient->createClient($accessToken, $expected);

                    return;
                }

                $update = $expected;
                unset($update['protocolMappers']);
                $update['attributes'] = (object) $this->mergeAttributes($existing['attributes'] ?? [], $expected['attributes']);
                $this->keycloakClient->updateClient($accessToken, $existing['id'], $update);
            });
            $record->markApplied();
        } catch (\Throwable $e) {
            $record->markFailed($e->getMessage());
            $this->em->persist($record);
            $this->em->flush();

            throw $e;
        }

        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    private function expectedClient(string $clientId, string $baseClientId, array $attributes, string $paradeUrl): array
    {
        $url = rtrim($paradeUrl, '/');
        $parts = parse_url($url);
        $origin = sprintf('%s://%s%s', $parts['scheme'] ?? 'https', $parts['host'] ?? '', isset($parts['port']) ? ':'.$parts['port'] : '');

        return [
            'clientId' => $clientId,
            'protocol' => 'openid-connect',
            'publicClient' => true,
            'clientAuthenticatorType' => 'none',
            'fullScopeAllowed' => true,
            'standardFlowEnabled' => true,
            'implicitFlowEnabled' => false,
            'directAccessGrantsEnabled' => false,
            'serviceAccountsEnabled' => false,
            'redirectUris' => [$url.'/*'],
            'webOrigins' => [$origin],
            'attributes' => [
                'pkce.code.challenge.method' => 'S256',
                'post.logout.redirect.uris' => $url.'/*',
                ...$this->provisioner->ownership($this->provisioner->parade()),
                ...$attributes,
            ],
            'protocolMappers' => [
                [
                    'name' => 'parade-main-audience',
                    'protocol' => 'openid-connect',
                    'protocolMapper' => 'oidc-audience-mapper',
                    'consentRequired' => false,
                    'config' => [
                        'included.client.audience' => $baseClientId,
                        'id.token.claim' => 'false',
                        'access.token.claim' => 'true',
                    ],
                ],
            ],
        ];
    }

    private function mergeAttributes(array $current, array $attributes): array
    {
        $kept = array_filter(
            $current,
            fn (string $key): bool => !str_starts_with($key, self::ATTRIBUTE_PREFIX),
            ARRAY_FILTER_USE_KEY,
        );

        $dropped = array_fill_keys(array_keys(array_diff_key(
            array_filter($current, fn (string $key): bool => str_starts_with($key, self::ATTRIBUTE_PREFIX), ARRAY_FILTER_USE_KEY),
            $attributes,
        )), '');

        return [...$kept, ...$dropped, ...$attributes];
    }
}
