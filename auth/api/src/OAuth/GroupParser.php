<?php

declare(strict_types=1);

namespace App\OAuth;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Jq;

class GroupParser
{
    private array $normalizers;

    public function __construct(array $normalizers)
    {
        $this->normalizers = $normalizers;
    }

    public function extractGroups(UserResponseInterface $response): ?array
    {
        $providerName = $response->getResourceOwner()->getName();
        $data = $response->getData();
        if (isset($this->normalizers[$providerName])) {
            $jq = Jq\Input::fromString(json_encode($data));
            $node = $jq->filter($this->normalizers[$providerName]);

            return $node['groups'] ?? null;
        }

        return ResponsePathExtractor::getValueForPath($response->getPaths(), $data, 'groups');
    }
}
