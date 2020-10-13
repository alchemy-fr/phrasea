<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

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

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publications', []);
        $json = json_decode($response->getContent(), true);

        foreach (range(1, $defaultLimit) as $i) {
            $this->assertEquals('Pub '.$this->addZero($i), $json[$i - 1]['title']);
        }

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publications?page=2', []);
        $json = json_decode($response->getContent(), true);

        foreach (range($defaultLimit + 1, $nbItems) as $i) {
            $this->assertEquals('Pub '.$this->addZero($i), $json[$i - 1 - $defaultLimit]['title']);
        }
    }

    public function testPublicationAssetsPagination(): void
    {
        // TODO
        $this->markTestIncomplete('Memory leak to be fixed');

        return;

        $em = self::getEntityManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        $pub = $this->createPublication([
            'no_flush' => true,
        ]);
        $nbItems = 50;
        $defaultLimit = 30;
        foreach (range(1, $nbItems) as $i) {
            $this->createAsset([
                'description' => 'Asset '.$this->addZero($i),
                'publication_id' => $pub,
                'no_flush' => true,
            ]);
        }
        $em->flush();
        $this->clearEmBeforeApiCall();

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publications/'.$pub.'/assets', []);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        //echo json_encode($json, JSON_PRETTY_PRINT);
        foreach (range(1, $defaultLimit) as $i) {
            $this->assertEquals($defaultLimit, count($json));
            $this->assertEquals('Asset '.$this->addZero($i), $json[$i - 1]['asset']['description']);
        }

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publications/'.$pub.'/assets?page=2', []);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals($nbItems - $defaultLimit, count($json));
        foreach (range($defaultLimit + 1, $nbItems) as $i) {
            $this->assertEquals('Asset '.$this->addZero($i), $json[$i - 1 - $defaultLimit]['asset']['description']);
        }
    }

    private function addZero(int $i): string
    {
        return $i >= 10 ? (string)$i : '0'.$i;
    }
}
