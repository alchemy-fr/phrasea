<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class OrderTest extends AbstractExposeTestCase
{
    public function testPublicationOrder(): void
    {
        $nbItems = 15;
        $pubList = range(1, $nbItems);
        foreach ($pubList as $i) {
            $this->createPublication([
                'title' => 'Pub '.$this->addZero($i),
                'no_flush' => true,
                'enabled' => true,
                'publiclyListed' => true,
            ]);
        }
        $em = self::getEntityManager();
        $em->flush();

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publications', []);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        foreach (range(1, $nbItems) as $i) {
            $this->assertEquals('Pub '.$this->addZero($i), $json[$i - 1]['title']);
        }

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publications?order[title]=desc', []);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        foreach (range(1, $nbItems) as $i) {
            $this->assertEquals('Pub '.$this->addZero($nbItems - $i + 1), $json[$i - 1]['title']);
        }
    }

    private function addZero(int $i): string
    {
        return $i >= 10 ? (string)$i : '0'.$i;
    }
}
