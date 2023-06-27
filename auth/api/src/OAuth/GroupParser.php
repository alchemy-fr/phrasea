<?php

declare(strict_types=1);

namespace App\OAuth;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Jq;

class GroupParser
{
    public function __construct(private array $normalizers)
    {
    }

    public function extractGroups(UserResponseInterface $response): ?array
    {
        $providerName = $response->getResourceOwner()->getName();
        $data = $response->getData();
        if (isset($this->normalizers[$providerName])) {
            $jq = Jq\Input::fromString(json_encode($data, JSON_THROW_ON_ERROR));
            $node = $jq->filter($this->normalizers[$providerName]);

            return $node['groups'] ?? null;
        }

        return ResponsePathExtractor::getValueForPath($response->getPaths(), $data, 'groups');
    }
}
