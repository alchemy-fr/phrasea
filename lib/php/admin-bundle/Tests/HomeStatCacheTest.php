<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Home\HomeStat;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class HomeStatCacheTest extends TestCase
{
    public function testCountsAreCachedPerLabel(): void
    {
        $dashboard = $this->createDashboard(new ArrayAdapter());
        $calls = 0;
        $counts = static function () use (&$calls): array {
            ++$calls;

            return ['total' => $calls * 10, 'last24h' => 1];
        };

        $first = $dashboard->stat('Assets', $counts)();
        $second = $dashboard->stat('Assets', $counts)();
        $this->assertSame(1, $calls);
        $this->assertSame(10, $first->total);
        $this->assertSame(10, $second->total);
        $this->assertSame(1, $second->last24h);

        $other = $dashboard->stat('Collections', $counts)();
        $this->assertSame(2, $calls);
        $this->assertSame(20, $other->total);
    }

    public function testFailureIsNotCached(): void
    {
        $dashboard = $this->createDashboard(new ArrayAdapter());
        $fail = true;
        $counts = static function () use (&$fail): array {
            if ($fail) {
                throw new \RuntimeException('Database unreachable');
            }

            return ['total' => 3];
        };

        $failed = $dashboard->stat('Assets', $counts)();
        $this->assertSame('Database unreachable', $failed->error);
        $this->assertNull($failed->total);

        $fail = false;
        $this->assertSame(3, $dashboard->stat('Assets', $counts)()->total);
    }

    private function createDashboard(ArrayAdapter $cache): object
    {
        $dashboard = new class extends AbstractAdminDashboardController {
            /**
             * @param callable(): array{total: int} $counts
             *
             * @return callable(): HomeStat
             */
            public function stat(string $label, callable $counts): callable
            {
                return $this->createHomeStat($label, 'fa-solid fa-image', $counts);
            }
        };
        $dashboard->setHomeCache($cache);
        $dashboard->setLogger(new NullLogger());

        return $dashboard;
    }
}
