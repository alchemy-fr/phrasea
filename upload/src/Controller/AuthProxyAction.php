<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\RequestForwarder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthProxyAction
{
    /**
     * @var RequestForwarder
     */
    private $requestForwarder;

    public function __construct(RequestForwarder $requestForwarder)
    {
        $this->requestForwarder = $requestForwarder;
    }

    /**
     * @Route(path="/oauth/v2/token", methods={"POST"})
     */
    public function __invoke(Request $request): Response
    {
        return $this->requestForwarder->forwardRequest($request);
    }
}
