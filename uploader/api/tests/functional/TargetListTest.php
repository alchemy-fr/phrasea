<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Target;

class TargetListTest extends AbstractUploaderTestCase
{
    /**
     * The "query" parameter the target select sends: a partial match on the name.
     */
    public function testTargetsAreSearchedByName(): void
    {
        $em = self::getEntityManager();
        foreach (['Databox production', 'Databox staging', 'Archive'] as $name) {
            $target = new Target();
            $target->setName($name);
            $target->setSlug(strtolower(str_replace(' ', '-', $name)));
            $target->setTargetUrl('http://localhost');
            $em->persist($target);
        }
        $em->flush();

        $this->assertEqualsCanonicalizing(['Databox production', 'Databox staging'], $this->listNames('/targets?query=databox'));
        $this->assertSame(['Archive'], $this->listNames('/targets?query=ARCH'));
    }

    /**
     * The targets are filtered by the user groups after the SQL query: a page of
     * them would come back short, or empty, with the allowed ones out of reach.
     */
    public function testEveryAllowedTargetIsListedBeyondThirty(): void
    {
        $em = self::getEntityManager();
        for ($i = 1; $i <= 35; ++$i) {
            $target = new Target();
            $target->setName(sprintf('A restricted %02d', $i));
            $target->setSlug(sprintf('a-restricted-%02d', $i));
            $target->setTargetUrl('http://localhost');
            $target->setAllowedGroups(['restricted-group']);
            $em->persist($target);
        }
        $em->flush();
        $allowed = ['Z allowed 1', 'Z allowed 2', 'Z allowed 3'];
        foreach ($allowed as $i => $name) {
            $target = new Target();
            $target->setName($name);
            $target->setSlug(sprintf('z-allowed-%d', $i + 1));
            $target->setTargetUrl('http://localhost');
            $em->persist($target);
        }
        $em->flush();

        $json = $this->list('/targets', KeycloakClientTestMock::USER_UID);
        $this->assertEqualsCanonicalizing($allowed, array_column($json['member'], 'name'));
        $this->assertSame(3, $json['totalItems']);
        $this->assertArrayNotHasKey('view', $json);

        $this->assertCount(38, $this->listNames('/targets'));
    }

    private function listNames(string $url, string $userUid = KeycloakClientTestMock::ADMIN_UID): array
    {
        return array_column($this->list($url, $userUid)['member'], 'name');
    }

    private function list(string $url, string $userUid): array
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor($userUid), 'GET', $url);
        $this->assertEquals(200, $response->getStatusCode());

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
