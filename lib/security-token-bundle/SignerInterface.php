<?php

declare(strict_types=1);

namespace Alchemy\SecurityTokenBundle;

use Symfony\Component\HttpFoundation\Request;

interface SignerInterface
{
    /**
     * Creates a signed token and return a cloned request with the token included.
     */
    public function signRequest(Request $request): Request;
}
