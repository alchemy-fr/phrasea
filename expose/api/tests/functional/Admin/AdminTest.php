<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTestCase;
use App\Admin\Home\ExposeHomeCharts;
use App\Entity\Asset;
use App\Entity\DownloadRequest;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

class AdminTest extends AbstractAdminTestCase
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    public function testHomePage(): void
    {
        $this->doTestHomePage([
            'Publications',
            'Assets',
            'Download requests',
            'Publication shares',
            'Profiles',
            'Failed messages',
        ], [
            'Latest publications',
            'Latest assets',
            'Latest download requests',
        ], [
            ExposeHomeCharts::ACTIVITY_TITLE,
            ExposeHomeCharts::FILE_TYPE_TITLE,
            ExposeHomeCharts::PUBLICATION_TITLE,
        ]);
    }

    public function testHomePageCharts(): void
    {
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
        // The database is not reset between the test classes
        $before = $this->getHomeChartConfigs();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $publication = new Publication();
        $publication->setTitle('Home charts');
        $em->persist($publication);

        $assets = [];
        foreach (['photo.jpg' => 'image/jpeg', 'doc.pdf' => 'application/pdf'] as $name => $mimeType) {
            $asset = new Asset();
            $asset->setPublication($publication);
            $asset->setPath('a/b/'.$name);
            $asset->setSize(1024);
            $asset->setOriginalName($name);
            $asset->setMimeType($mimeType);
            $em->persist($asset);
            $assets[] = $asset;
        }

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setEmail('reader@example.com');
        $downloadRequest->setPublication($publication);
        $downloadRequest->setAsset($assets[0]);
        $em->persist($downloadRequest);
        $em->flush();

        $after = $this->getHomeChartConfigs();
        $this->assertCount(3, $after);

        $activity = $after[ExposeHomeCharts::ACTIVITY_TITLE];
        $this->assertNotNull($activity);
        $this->assertSame(['Publications', 'Assets', 'Download requests'], array_column($activity['data']['datasets'], 'label'));
        $this->assertSame([1, 2, 1], array_map(
            static fn (int $i): int => self::getChartValue($activity, $i, -1) - self::getChartValue($before[ExposeHomeCharts::ACTIVITY_TITLE], $i, -1),
            [0, 1, 2],
        ));

        $fileTypes = $after[ExposeHomeCharts::FILE_TYPE_TITLE];
        $this->assertNotNull($fileTypes);
        foreach (['Image', 'Document'] as $family) {
            $this->assertSame(1, self::getChartValue($fileTypes, 0, $family) - self::getChartValue($before[ExposeHomeCharts::FILE_TYPE_TITLE], 0, $family), $family);
        }

        $publications = $after[ExposeHomeCharts::PUBLICATION_TITLE];
        $this->assertNotNull($publications);
        if (in_array('Home charts', $publications['data']['labels'], true)) {
            $this->assertSame(2, self::getChartValue($publications, 0, 'Home charts'));
            $this->assertSame(2048, self::getChartValue($publications, 1, 'Home charts'));
        } else {
            // Ten publications with more assets were left by other tests
            $this->assertCount(10, $publications['data']['labels']);
        }
    }

    /**
     * @return array<string, array<string, mixed>|null> the Chart.js configuration of each chart, indexed
     *                                                  by title, null for a chart without data
     */
    private function getHomeChartConfigs(): array
    {
        $crawler = $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();

        $charts = $crawler->filter('.admin-home-chart');
        $this->assertCount(0, $charts->filter('.text-danger'), $charts->text(''));

        $configs = [];
        foreach ($charts as $node) {
            $chart = new Crawler($node);
            $canvas = $chart->filter('canvas[data-chart]');
            $configs[trim($chart->filter('.card-header > span')->text())] = $canvas->count() > 0
                ? json_decode((string) $canvas->attr('data-chart'), true, 512, JSON_THROW_ON_ERROR)
                : null;
        }

        return $configs;
    }

    /**
     * @param array<string, mixed>|null $config
     * @param int|string                $label  the label, or its index (negative from the end)
     */
    private static function getChartValue(?array $config, int $datasetIndex, int|string $label): int
    {
        if (null === $config) {
            return 0;
        }

        $labels = $config['data']['labels'];
        $index = is_int($label) ? ($label < 0 ? count($labels) + $label : $label) : array_search($label, $labels, true);
        if (false === $index) {
            return 0;
        }

        return (int) $config['data']['datasets'][$datasetIndex]['data'][$index];
    }

    public function testRealtimePage(): void
    {
        $this->doTestRealtimePage();
    }

    public function testQueuesPage(): void
    {
        $this->doTestQueuesPage();
    }
}
