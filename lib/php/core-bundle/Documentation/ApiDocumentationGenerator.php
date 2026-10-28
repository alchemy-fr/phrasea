<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Documentation;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

final class ApiDocumentationGenerator extends DocumentationGenerator
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

    public function getContent(): string
    {
        // The documentation builder (docusaurus-plugin-openapi-docs) does not
        // read OpenAPI 3.2, the API Platform default. Written to a file: going
        // through the console formatter exhausts the default memory limit.
        $file = tempnam(sys_get_temp_dir(), 'openapi');
        $input = new ArrayInput([
            'command' => 'api:openapi:export',
            '--spec-version' => '3.0.0',
            '--output' => $file,
        ]);
        $output = new BufferedOutput();
        try {
            if (Command::SUCCESS !== $this->application->run($input, $output)) {
                throw new \RuntimeException(sprintf('Unable to generate OpenAPI schema: %s', $output->fetch()));
            }

            return file_get_contents($file);
        } finally {
            unlink($file);
        }
    }
}
