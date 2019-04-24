<?php

declare(strict_types=1);

namespace App\Http;

use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestForwarder
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function forwardRequest(Request $request): Response
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        /** @var ServerRequest $psrRequest */
        $psrRequest = $psrHttpFactory->createRequest($request);
        $psrRequest = $psrRequest->withUri(new Uri($request->getRequestUri()), false);

        $guzzleResponse = $this->client->send($psrRequest);

        $httpFoundationFactory = new HttpFoundationFactory();
        $response = $httpFoundationFactory->createResponse($guzzleResponse);

        return $response;
    }
}
