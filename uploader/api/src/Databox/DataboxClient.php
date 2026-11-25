<?php

declare(strict_types=1);

namespace App\Databox;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Consumer\TargetPrivateOrigins;
use App\Entity\Target;
use App\Security\UploadLinkUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls Databox with the access token of the user: Databox applies its own rights,
 * the uploader never sees more than the user could.
 *
 * Nobody is behind an upload link: its holder only reads the values of the entity lists, as the
 * Uploader integration of Databox the target of the link commits to (`Authorization: ApiKey
 * <integration ID>:<key>`), with the key the target already sends to it. Databox answers their
 * approved values only, of the lists of its workspace the owner of the integration could read.
 */
#[AsAlias(DataboxClientInterface::class)]
#[Autoconfigure(public: true)]
final readonly class DataboxClient implements DataboxClientInterface
{
    private const string LINK_ALLOWED_PATH = '/attribute-entities';
    private const string INTEGRATION_URL_PATTERN = '~^(.+)/integrations/uploader/([0-9a-fA-F-]+)/incoming-commit$~';
    private const int LINK_MAX_DURATION = 30;

    public function __construct(
        private HttpClientInterface $client,
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $em,
        private TargetPrivateOrigins $privateOrigins,
        #[Autowire(env: 'DATABOX_API_URL')]
        private string $databoxApiUrl,
    ) {
    }

    public function get(string $path, array $query = []): array
    {
        $user = $this->security->getUser();
        if ($user instanceof UploadLinkUser) {
            return $this->getForLink($user, $path, $query);
        }

        if ('' === trim($this->databoxApiUrl)) {
            throw new DataboxException('Databox is not configured (DATABOX_API_URL)', 503);
        }
        if (!$user instanceof JwtUser) {
            throw new DataboxException('Databox can only be queried on behalf of a user', 401);
        }

        return $this->request($this->client, rtrim($this->databoxApiUrl, '/').$path, $path, $query, [
            'Authorization' => 'Bearer '.$user->getJwt(),
            'Accept' => 'application/ld+json',
        ]);
    }

    private function getForLink(UploadLinkUser $user, string $path, array $query): array
    {
        if (self::LINK_ALLOWED_PATH !== $path) {
            throw new DataboxException(sprintf('%s cannot be read through an upload link', $path), 403);
        }

        $target = $this->em->find(Target::class, $user->getTargetId());
        $authorizationKey = $target?->getAuthorizationKey();
        // Databox authenticates an integration by the ApiKey scheme only: any other would read it anonymously
        if (
            !$target instanceof Target
            || !preg_match(self::INTEGRATION_URL_PATTERN, (string) $target->getTargetUrl(), $matches)
            || null === $authorizationKey || '' === $authorizationKey
            || 'avoid' === $authorizationKey
            || AssetConsumerNotifyHandler::DEFAULT_AUTHORIZATION_SCHEME !== ($target->getAuthorizationScheme() ?? AssetConsumerNotifyHandler::DEFAULT_AUTHORIZATION_SCHEME)
        ) {
            throw new DataboxException('The target of the link is not bound to an Uploader integration of Databox', 403);
        }

        [, $databoxApiUrl, $integrationId] = $matches;
        $url = $databoxApiUrl.$path;
        $options = ['max_duration' => self::LINK_MAX_DURATION];
        // Same network policy as the notification of the commits to the target (AssetConsumerNotifyHandler)
        if ($this->privateOrigins->isAllowed($url)) {
            $client = $this->client;
            $options['max_redirects'] = 0;
        } else {
            $client = new NoPrivateNetworkHttpClient($this->client);
        }

        return $this->request($client, $url, $path, $query, [
            'Authorization' => sprintf('%s %s:%s', AssetConsumerNotifyHandler::DEFAULT_AUTHORIZATION_SCHEME, $integrationId, $authorizationKey),
            'Accept' => 'application/ld+json',
        ], $options);
    }

    private function request(HttpClientInterface $client, string $url, string $path, array $query, array $headers, array $options = []): array
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        if (null !== $locale) {
            $headers['Accept-Language'] = $locale;
        }

        try {
            return $client->request('GET', $url, [
                'query' => $query,
                'headers' => $headers,
                ...$options,
            ])->toArray();
        } catch (HttpExceptionInterface $e) {
            $status = $e->getResponse()->getStatusCode();

            throw new DataboxException(sprintf('Databox answered %d on %s', $status, $path), $status, $e);
        } catch (TransportExceptionInterface|DecodingExceptionInterface $e) {
            throw new DataboxException(sprintf('Databox is unreachable: %s', $e->getMessage()), 502, $e);
        }
    }
}
