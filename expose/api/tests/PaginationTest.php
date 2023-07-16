<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;

class PaginationTest extends AbstractExposeTestCase
{
    public function testPublicationPagination(): void
    {
        $defaultLimit = 30;
        $nbItems = 50;
        $pubList = range(1, $nbItems);
        foreach ($pubList as $i) {
            $this->createPublication([
                'title' => 'Pub '.$this->addZero($i),
                'no_flush' => true,
            ]);
        }
        $em = self::getEntityManager();
        $em->flush();

        $response = $this->request(OAuthClientTestMock::USER_TOKEN, 'GET', '/publications');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        foreach (range(1, $defaultLimit) as $i) {
            $this->assertEquals('Pub '.$this->addZero($i), $json[$i - 1]['title']);
        }

        $response = $this->request(OAuthClientTestMock::USER_TOKEN, 'GET', '/publications?page=2');
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        foreach (range($defaultLimit + 1, $nbItems) as $i) {
            $this->assertEquals('Pub '.$this->addZero($i), $json[$i - 1 - $defaultLimit]['title']);
        }
    }

    private function addZero(int $i): string
    {
        return $i >= 10 ? (string) $i : '0'.$i;
    }
}
