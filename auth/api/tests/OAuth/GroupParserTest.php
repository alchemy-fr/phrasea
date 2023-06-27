<?php

declare(strict_types=1);

namespace App\Tests\OAuth;

use App\OAuth\GroupParser;
use App\OAuth\ResourceOwner\ResourceOwnerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\PathUserResponse;
use PHPUnit\Framework\TestCase;

class GroupParserTest extends TestCase
{
    public function testJq(): void
    {
        $gp = new GroupParser([
            'foo' => '.user_authorization[] | select(.resource == "phrasea") | {groups: [.permissions[].name]}',
        ]);

        $resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $resourceOwner->method('getName')
            ->willReturn('foo');

        $response = new PathUserResponse();
        $response->setResourceOwner($resourceOwner);
        $response->setData(<<<JSON
{
  "user_authorization": [
    {
      "resource": "phrasea",
      "permissions": [
        {
          "name": "UPLOAD_BU_FINANCE"
        },
        {
          "name": "ADMIN_BU_FINANCE"
        }
      ],
      "resource_id": "543647ec-e1d4-4fc4-9e06-c6bbf450fece"
    },
    {
      "resource": "other",
      "permissions": [
        {
          "name": "FOO"
        },
        {
          "name": "BAR"
        }
      ],
      "resource_id": "aaaa"
    }
  ]
}
JSON
        );
        $groups = $gp->extractGroups($response);

        $this->assertEquals([
            'UPLOAD_BU_FINANCE',
            'ADMIN_BU_FINANCE',
        ], $groups);
    }
}
