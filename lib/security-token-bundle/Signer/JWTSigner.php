<?php

declare(strict_types=1);

namespace Alchemy\SecurityTokenBundle\Signer;

use Alchemy\SecurityTokenBundle\SignerInterface;
use Symfony\Component\HttpFoundation\Request;

class JWTSigner implements SignerInterface
{
    public function signRequest(Request $request): Request
    {
        $signedRequest = clone $request;


        $token = ''; // TODO

        $signedRequest->query->set('token='.$token);

        return $signedRequest;
    }
}
