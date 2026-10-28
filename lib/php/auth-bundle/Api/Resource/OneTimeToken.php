<?php

namespace Alchemy\AuthBundle\Api\Resource;

use Alchemy\AuthBundle\Api\Processor\UserOneTimeTokenProcessor;
use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/ott',
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            validate: false,
            // Not "one-time-token": the documentation builder derives the same page id from the "OneTimeToken" tag.
            name: 'create_one_time_token',
            processor: UserOneTimeTokenProcessor::class,
        ),
    ]
)]
final class OneTimeToken
{
    private string $token;

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }
}
