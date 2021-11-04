<?php

declare(strict_types=1);

namespace App\External;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Promise;

/**
 * @method ResponseInterface get(string|UriInterface $uri, array $options = [])
 * @method ResponseInterface head(string|UriInterface $uri, array $options = [])
 * @method ResponseInterface put(string|UriInterface $uri, array $options = [])
 * @method ResponseInterface post(string|UriInterface $uri, array $options = [])
 * @method ResponseInterface patch(string|UriInterface $uri, array $options = [])
 * @method ResponseInterface delete(string|UriInterface $uri, array $options = [])
 * @method Promise\PromiseInterface getAsync(string|UriInterface $uri, array $options = [])
 * @method Promise\PromiseInterface headAsync(string|UriInterface $uri, array $options = [])
 * @method Promise\PromiseInterface putAsync(string|UriInterface $uri, array $options = [])
 * @method Promise\PromiseInterface postAsync(string|UriInterface $uri, array $options = [])
 * @method Promise\PromiseInterface patchAsync(string|UriInterface $uri, array $options = [])
 * @method Promise\PromiseInterface deleteAsync(string|UriInterface $uri, array $options = [])
 */
class PhraseanetApiClient
{
    private Client $client;
    private string $oauthToken;

    public function __construct(Client $client, string $oauthToken)
    {
        if (empty($oauthToken)) {
            throw new InvalidArgumentException('Phraseanet token is empty');
        }

        $handler = $client->getConfig('handler');
        $handler->unshift(Middleware::mapRequest(function (RequestInterface $request): RequestInterface {
            return $request->withUri(Uri::withQueryValue($request->getUri(), 'oauth_token', $this->oauthToken));
        }));

        $this->client = $client;
        $this->oauthToken = $oauthToken;
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->client, $name], $arguments);
    }
}
