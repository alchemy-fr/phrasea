<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Documentation;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class ApiDocumentationGenerator extends DocumentationGenerator
{
    private Application $application;

    public function __construct(KernelInterface $kernel)
    {
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);
    }

    public function getPath(): string
    {
        return '_schema.json';
    }

    public function getTitle(): string
    {
        return 'API Schema';
    }

    public function getContent(): string
    {
        $input = new ArrayInput([
            'command' => 'api:openapi:export',
        ]);
        $output = new BufferedOutput();
        $this->application->run($input, $output);

        return $output->fetch();
    }
}
