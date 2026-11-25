<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AdminBundle\Tests\AbstractAdminTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Admin\Home\UploaderHomeCharts;
use App\Controller\Admin\GlobalPermissionsController;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

class AdminTest extends AbstractAdminTestCase
{
    use FixturesTrait;

    /**
     * Restores the empty database at each boot, as the other functional tests expect.
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    public function testHomePage(): void
    {
        $this->doTestHomePage([
            'Commits',
            'Uploaded assets',
            'Uploaded volume',
            'Unacknowledged commits',
            'Targets',
            'Upload links',
            'Failed messages',
        ], [
            'Latest commits',
            'Latest upload links used',
        ], [
            UploaderHomeCharts::UPLOADS_TITLE,
            UploaderHomeCharts::FILE_TYPE_TITLE,
            UploaderHomeCharts::TARGET_TITLE,
        ]);
    }

    public function testHomePageActivity(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $target = new Target();
        $target->setName('Home target');
        $target->setSlug('home-target');
        $target->setTargetUrl('http://localhost');
        $em->persist($target);

        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setTotalSize(2048);
        $commit->setUserId(KeycloakClientTestMock::ADMIN_UID);
        $commit->setToken('secret_token');
        $em->persist($commit);

        foreach (['a.jpeg', 'b.jpeg'] as $name) {
            $asset = new Asset();
            $asset->setTarget($target);
            $asset->setMimeType('image/jpeg');
            $asset->setCommit($commit);
            $asset->setPath('a/b/'.$name);
            $asset->setSize(1024);
            $asset->setOriginalName($name);
            $asset->setUserId(KeycloakClientTestMock::ADMIN_UID);
            $em->persist($asset);
        }
        $em->flush();

        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $crawler = $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();

        $charts = $crawler->filter('.admin-home-chart');
        $this->assertCount(3, $charts);
        $this->assertCount(0, $charts->filter('.text-danger'), $charts->text(''));
        $configs = [];
        foreach ($charts as $node) {
            $chart = new Crawler($node);
            $configs[trim($chart->filter('.card-header > span')->text())] = json_decode((string) $chart->filter('canvas[data-chart]')->attr('data-chart'), true, 512, JSON_THROW_ON_ERROR);
        }
        $uploads = $configs[UploaderHomeCharts::UPLOADS_TITLE]['data']['datasets'];
        $this->assertSame(1, $uploads[0]['data'][29]);
        $this->assertSame(2048, $uploads[1]['data'][29]);
        $this->assertSame('line', $uploads[1]['type']);
        $this->assertSame(['Image'], $configs[UploaderHomeCharts::FILE_TYPE_TITLE]['data']['labels']);
        $this->assertSame([2], $configs[UploaderHomeCharts::FILE_TYPE_TITLE]['data']['datasets'][0]['data']);
        $this->assertSame(['Home target'], $configs[UploaderHomeCharts::TARGET_TITLE]['data']['labels']);
        $this->assertSame([1], $configs[UploaderHomeCharts::TARGET_TITLE]['data']['datasets'][0]['data']);

        $stats = $crawler->filter('.admin-home-stat');
        $this->assertStringContainsString('2.05 kB', $stats->eq(2)->text());
        $this->assertSame('24h 1', $stats->eq(0)->filter('.admin-home-period')->first()->text());

        $row = $crawler->filter('.admin-home-block')->first()->filter('tbody tr')->first();
        $this->assertStringContainsString('Home target', $row->text());
        $this->assertStringContainsString('2 assets, 2.05 kB', $row->text());
        $this->assertSame('admin (Test User)', trim($row->filter('td')->eq(3)->text()));
        $this->assertStringContainsString('Pending', $row->filter('.badge.text-bg-warning')->text());
        $this->assertStringContainsString($commit->getId(), (string) $row->filter('a')->attr('href'));
    }

    public function testTargetAuthorizationKeyIsNeverShown(): void
    {
        $key = 'k3y-n0t-sh0wn';
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $target = new Target();
        $target->setName('Keyed target');
        $target->setTargetUrl('https://example.com/upload');
        $target->setAuthorizationKey($key);
        $em->persist($target);
        $em->flush();
        $id = $target->getId();

        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $editUrl = static::getContainer()->get('router')->generate('easyadmin_target_edit', ['entityId' => $id]);

        $crawler = $this->client->request('GET', $editUrl);
        $this->assertResponseIsSuccessful();
        $input = $crawler->filter('input[name="Target[authorizationKey]"]');
        $this->assertSame('password', $input->attr('type'));
        $this->assertSame('', (string) $input->attr('value'));
        $this->assertStringNotContainsString($key, (string) $this->client->getResponse()->getContent());

        $getKey = function () use ($id): ?string {
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();

            return $em->find(Target::class, $id)->getAuthorizationKey();
        };
        $save = function (array $values) use ($editUrl): void {
            $crawler = $this->client->request('GET', $editUrl);
            $this->client->submit($crawler->selectButton('Save changes')->form(), $values);
            $this->assertResponseRedirects();
        };

        $save(['Target[name]' => 'Renamed', 'Target[targetUrl]' => 'https://example.com/other-path']);
        $this->assertSame($key, $getKey());

        $save(['Target[authorizationKey]' => 'new-key']);
        $this->assertSame('new-key', $getKey());

        $save(['Target[targetUrl]' => 'https://elsewhere.example.com/upload']);
        $this->assertNull($getKey());

        $save(['Target[targetUrl]' => 'https://example.com/upload', 'Target[authorizationKey]' => 'moved-key']);
        $this->assertSame('moved-key', $getKey());
    }

    public function testRealtimePage(): void
    {
        $this->doTestRealtimePage();
    }

    public function testQueuesPage(): void
    {
        $this->doTestQueuesPage();
    }

    public function testGlobalPermissionPages(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        $router = static::getContainer()->get('router');

        foreach ([
            GlobalPermissionsController::ROUTE_FORM_SCHEMA => 'form_schema',
            GlobalPermissionsController::ROUTE_TARGET_DATA => 'target_params',
            GlobalPermissionsController::ROUTE_UPLOAD_LINK => 'upload_link',
        ] as $route => $objectType) {
            $this->client->request('GET', $router->generate($route));
            $this->assertResponseIsSuccessful($route);
            $this->assertStringContainsString('Permissions on all <b>'.$objectType.'</b>', (string) $this->client->getResponse()->getContent(), $route);
        }
    }
}
