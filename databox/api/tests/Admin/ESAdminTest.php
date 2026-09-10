<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use FOS\ElasticaBundle\Elastica\Client;
use Symfony\Component\DomCrawler\Crawler;

class ESAdminTest extends AbstractAdminTest
{
    private const string TMP_INDEX = 'alchemy_es_admin_test_tmp';
    private const string TMP_ALIAS = 'alchemy_es_admin_test_alias';

    private Client $es;

    public function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $this->client->disableReboot();

        $this->es = static::getContainer()->get(Client::class);
        $this->dropTmpIndex();
        $this->es->indices()->create(['index' => self::TMP_INDEX]);
    }

    public function tearDown(): void
    {
        $this->dropTmpIndex();
        parent::tearDown();
    }

    public function testIndexPageListsLogicalAndPhysicalIndices(): void
    {
        $crawler = $this->loadIndexPage();

        $this->assertStringContainsString('asset_test', $crawler->filter('#es-logical-indices')->text());
        $this->assertStringContainsString(self::TMP_INDEX, $crawler->filter('#es-physical-indices')->text());
        $this->assertGreaterThan(0, $crawler->filter('#es-physical-indices .js-es-confirm')->count());
    }

    public function testDetailPageRendersSettingsAndMappings(): void
    {
        $crawler = $this->client->request('GET', '/admin/elasticsearch/index/asset_test');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('"creation_date"', $crawler->filter('pre')->first()->text());
    }

    public function testDetailOfUnknownIndexRedirectsWithError(): void
    {
        $this->client->request('GET', '/admin/elasticsearch/index/does_not_exist_xyz');
        $this->assertResponseRedirects('/admin/elasticsearch');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('does not exist', $crawler->filter('#flash-messages')->text());
    }

    public function testAliasLifecycle(): void
    {
        $token = $this->getCsrfToken();

        // add
        $this->client->request('POST', '/admin/elasticsearch/alias/add', ['token' => $token, 'index' => self::TMP_INDEX, 'alias' => self::TMP_ALIAS]);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $this->assertSame([self::TMP_INDEX], $this->getAliasTargets(self::TMP_ALIAS));

        // switch to another index: the alias must leave the tmp index
        $this->client->request('POST', '/admin/elasticsearch/alias/switch', ['token' => $token, 'index' => 'asset_test', 'alias' => self::TMP_ALIAS]);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $this->assertSame(['asset_test'], $this->getAliasTargets(self::TMP_ALIAS));

        // remove
        $this->client->request('POST', '/admin/elasticsearch/alias/remove', ['token' => $token, 'index' => 'asset_test', 'alias' => self::TMP_ALIAS]);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $this->assertSame([], $this->getAliasTargets(self::TMP_ALIAS));
    }

    public function testCloseOpenAndDeleteIndex(): void
    {
        $token = $this->getCsrfToken();

        $this->client->request('POST', '/admin/elasticsearch/index/'.self::TMP_INDEX.'/close', ['token' => $token]);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $this->assertSame('close', $this->getIndexStatus(self::TMP_INDEX));

        $this->client->request('POST', '/admin/elasticsearch/index/'.self::TMP_INDEX.'/open', ['token' => $token]);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $this->assertSame('open', $this->getIndexStatus(self::TMP_INDEX));

        $this->client->request('POST', '/admin/elasticsearch/index/'.self::TMP_INDEX.'/delete', ['token' => $token]);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('has been deleted', $crawler->filter('#flash-messages')->text());
        $this->assertFalse($this->es->indices()->exists(['index' => self::TMP_INDEX])->asBool());
    }

    public function testMutationWithInvalidCsrfTokenDoesNothing(): void
    {
        $this->client->request('POST', '/admin/elasticsearch/index/'.self::TMP_INDEX.'/delete', ['token' => 'invalid']);
        $this->assertResponseRedirects('/admin/elasticsearch');
        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Invalid CSRF token', $crawler->filter('#flash-messages')->text());
        $this->assertTrue($this->es->indices()->exists(['index' => self::TMP_INDEX])->asBool());
    }

    public function testMutationsRejectGet(): void
    {
        $this->client->request('GET', '/admin/elasticsearch/index/'.self::TMP_INDEX.'/delete');
        $this->assertResponseStatusCodeSame(405);
        $this->assertTrue($this->es->indices()->exists(['index' => self::TMP_INDEX])->asBool());
    }

    private function loadIndexPage(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/elasticsearch');
        $response = $this->client->getResponse();
        if (200 !== $response->getStatusCode()) {
            echo $response->getContent();
        }
        $this->assertEquals(200, $response->getStatusCode());

        return $crawler;
    }

    private function getCsrfToken(): string
    {
        $crawler = $this->loadIndexPage();
        $token = $crawler->filter('form.es-action-form input[name="token"]')->first()->attr('value');
        $this->assertNotEmpty($token);

        return $token;
    }

    /**
     * @return list<string>
     */
    private function getAliasTargets(string $alias): array
    {
        try {
            $targets = array_keys($this->es->indices()->getAlias(['name' => $alias])->asArray());
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
            $targets = [];
        }
        sort($targets);

        return $targets;
    }

    private function getIndexStatus(string $index): string
    {
        $rows = $this->es->cat()->indices(['index' => $index, 'format' => 'json', 'h' => 'status'])->asArray();

        return $rows[0]['status'];
    }

    private function dropTmpIndex(): void
    {
        if ($this->es->indices()->exists(['index' => self::TMP_INDEX])->asBool()) {
            $this->es->indices()->delete(['index' => self::TMP_INDEX]);
        }
    }
}
