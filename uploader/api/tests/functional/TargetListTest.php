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

    private function listNames(string $url): array
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', $url);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return array_column($json['member'], 'name');
    }
}
