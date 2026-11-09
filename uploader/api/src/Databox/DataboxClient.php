<?php

declare(strict_types=1);

namespace App\Databox;

use Alchemy\AuthBundle\Security\JwtUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls Databox with the access token of the user: Databox applies its own rights,
 * the uploader never sees more than the user could.
 */
#[AsAlias(DataboxClientInterface::class)]
#[Autoconfigure(public: true)]
final readonly class DataboxClient implements DataboxClientInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private Security $security,
        private RequestStack $requestStack,
        #[Autowire(env: 'DATABOX_API_URL')]
        private string $databoxApiUrl,
    ) {
    }

    public function get(string $path, array $query = []): array
    {
        if ('' === trim($this->databoxApiUrl)) {
            throw new DataboxException('Databox is not configured (DATABOX_API_URL)', 503);
        }

        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            throw new DataboxException('Databox can only be queried on behalf of a user', 401);
        }

        $headers = [
            'Authorization' => 'Bearer '.$user->getJwt(),
            'Accept' => 'application/ld+json',
        ];
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        if (null !== $locale) {
            $headers['Accept-Language'] = $locale;
        }

        try {
            return $this->client->request('GET', rtrim($this->databoxApiUrl, '/').$path, [
                'query' => $query,
                'headers' => $headers,
            ])->toArray();
        } catch (HttpExceptionInterface $e) {
            $status = $e->getResponse()->getStatusCode();

            throw new DataboxException(sprintf('Databox answered %d on %s', $status, $path), $status, $e);
        } catch (TransportExceptionInterface|DecodingExceptionInterface $e) {
            throw new DataboxException(sprintf('Databox is unreachable: %s', $e->getMessage()), 502, $e);
        }
    }
}
