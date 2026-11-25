<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Fixture;

use App\Service\Fixture\FixtureAnalysisCache;
use PHPUnit\Framework\TestCase;

class FixtureAnalysisCacheTest extends TestCase
{
    private string $dir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/fixture-analysis-cache-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->cacheDir = $this->dir.'/cache';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function testADisabledCacheAlwaysComputesAndWritesNothing(): void
    {
        $cache = new FixtureAnalysisCache(false, $this->cacheDir);
        $path = $this->createImage('a.jpg', 'image-a');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['n' => $calls];
        };

        $this->assertSame(['n' => 1], $cache->remember('faces', $path, $compute));
        $this->assertSame(['n' => 2], $cache->remember('faces', $path, $compute));
        $this->assertSame(2, $calls);
        $this->assertDirectoryDoesNotExist($this->cacheDir);
    }

    public function testTheSameContentIsComputedOnceWhateverItsPath(): void
    {
        $cache = new FixtureAnalysisCache(true, $this->cacheDir);
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['vector' => [0.5, 1.0], 'model' => 'm'];
        };

        $first = $cache->remember('similarity', $this->createImage('a.jpg', 'same-bytes'), $compute);
        $second = $cache->remember('similarity', $this->createImage('b.jpg', 'same-bytes'), $compute);

        $this->assertSame(1, $calls);
        $this->assertSame(['vector' => [0.5, 1.0], 'model' => 'm'], $second);
        $this->assertSame($first, $second);
        $this->assertFileExists($this->cacheDir.'/similarity/'.hash('sha256', 'same-bytes').'.json');
        $this->assertCount(1, glob($this->cacheDir.'/similarity/*') ?: []);
    }

    public function testADifferentContentIsComputedAgain(): void
    {
        $cache = new FixtureAnalysisCache(true, $this->cacheDir);
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['n' => $calls];
        };

        $this->assertSame(['n' => 1], $cache->remember('faces', $this->createImage('a.jpg', 'image-a'), $compute));
        $this->assertSame(['n' => 2], $cache->remember('faces', $this->createImage('b.jpg', 'image-b'), $compute));
        $this->assertSame(2, $calls);
    }

    public function testNamespacesAreSeparate(): void
    {
        $cache = new FixtureAnalysisCache(true, $this->cacheDir);
        $path = $this->createImage('a.jpg', 'image-a');

        $this->assertSame(['ns' => 'faces'], $cache->remember('faces', $path, fn (): array => ['ns' => 'faces']));
        $this->assertSame(['ns' => 'similarity'], $cache->remember('similarity', $path, fn (): array => ['ns' => 'similarity']));
        $this->assertSame(['ns' => 'faces'], $cache->remember('faces', $path, fn (): array => ['ns' => 'other']));
    }

    public function testAFailedComputationWritesNothing(): void
    {
        $cache = new FixtureAnalysisCache(true, $this->cacheDir);
        $path = $this->createImage('a.jpg', 'image-a');

        try {
            $cache->remember('faces', $path, function (): array {
                throw new \RuntimeException('Service down');
            });
            $this->fail('The exception should be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Service down', $e->getMessage());
        }

        $this->assertSame([], glob($this->cacheDir.'/faces/*') ?: []);
        $this->assertSame(['ok' => true], $cache->remember('faces', $path, fn (): array => ['ok' => true]));
    }

    public function testACorruptedEntryIsComputedAndRewritten(): void
    {
        $cache = new FixtureAnalysisCache(true, $this->cacheDir);
        $path = $this->createImage('a.jpg', 'image-a');
        $file = $this->cacheDir.'/faces/'.hash('sha256', 'image-a').'.json';
        mkdir(\dirname($file), 0777, true);
        file_put_contents($file, '{"truncated');

        $this->assertSame(['faces' => []], $cache->remember('faces', $path, fn (): array => ['faces' => []]));
        $this->assertSame(['faces' => []], json_decode((string) file_get_contents($file), true));
        $this->assertSame(['faces' => []], $cache->remember('faces', $path, fn (): array => ['faces' => ['other']]));
    }

    private function createImage(string $name, string $content): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
