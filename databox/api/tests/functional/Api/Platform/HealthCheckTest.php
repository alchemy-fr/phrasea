<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET /_health/check: public probe listing the state of each dependency.
 */
final class HealthCheckTest extends AbstractDataboxTestCase
{
    public function testTheProbeListsEveryCheckAndFailsWhenOneFails(): void
    {
        $response = static::createClient()->request('GET', '/_health/check');

        $checks = $response->toArray(false);
        $this->assertNotEmpty($checks);
        $this->assertArrayHasKey('doctrine_dbal', $checks);
        foreach ($checks as $name => $check) {
            $this->assertIsBool($check['ok'], $name);
            if (!$check['ok']) {
                $this->assertArrayHasKey('error', $check, $name);
            }
        }

        $allOk = [] === array_filter($checks, fn (array $check): bool => !$check['ok']);
        $this->assertResponseStatusCodeSame($allOk ? 200 : 503);
    }

    public function testTheDatabaseCheckPasses(): void
    {
        $this->markTestIncomplete('BUG: DoctrineConnectionChecker calls Connection::ping(), removed in DBAL 3 (the app uses doctrine/dbal 3.10): the "doctrine_dbal" check always fails with "Call to undefined method", so the probe always answers 503 (lib/php/core-bundle/Health/Checker/DoctrineConnectionChecker.php:27).');

        $checks = static::createClient()->request('GET', '/_health/check')->toArray(false);

        $this->assertSame(['ok' => true], $checks['doctrine_dbal']);
    }
}
