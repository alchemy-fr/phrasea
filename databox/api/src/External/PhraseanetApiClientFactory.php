<?php

declare(strict_types=1);

namespace App\External;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

class PhraseanetApiClientFactory
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function create(string $baseUri, string $oauthToken): Client
    {
        if (empty($oauthToken)) {
            throw new InvalidArgumentException('Phraseanet token is empty');
        }

        $options = array_merge($this->options, [
            'base_uri' => $baseUri,
        ]);

        $client = new Client($options);

        $handler = $client->getConfig('handler');
        $handler->unshift(Middleware::mapRequest(function (RequestInterface $request) use ($oauthToken): RequestInterface {
            return $request
                ->withAddedHeader('Authorization', 'OAuth '.$oauthToken);
        }));

        return $client;
    }
}
