<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Loader;

use Alchemy\Workflow\Loader\YamlLoader;
use PHPUnit\Framework\TestCase;

class YamlLoaderTest extends TestCase
{
    public function testLoadYamlOK(): void
    {
        $loader = new YamlLoader();
        $workflow = $loader->load(__DIR__.'/../fixtures/file-manipulator.yaml');

        $this->assertEquals('Manipulate file', $workflow->getName());
        $this->assertCount(2, $workflow->getJobs());
        $this->assertArrayHasKey('copy-files', $workflow->getJobs());
        $copyFilesJob = $workflow->getJobs()['copy-files'];
        $this->assertCount(3, $copyFilesJob->getSteps());
        $this->assertCount(0, $copyFilesJob->getEnv());
        $this->assertEquals('env.foo == "bar"', $copyFilesJob->getIf());
        $this->assertNull($copyFilesJob->getSteps()[0]->getIf());
    }
}
