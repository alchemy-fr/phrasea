<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\RequestForwarder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MeProxyAction
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
     * @Route(path="/me", methods={"GET"})
     */
    public function __invoke(Request $request): Response
    {
        return $this->requestForwarder->forwardRequest($request);
    }
}
