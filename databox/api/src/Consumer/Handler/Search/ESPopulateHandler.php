<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\Listener\PopulatePassListener;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ESPopulateHandler
{
    public function __construct(
        private KernelInterface $kernel,
        private PopulatePassListener $populatePassListener,
    ) {
    }

    public function __invoke(ESPopulate $message): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $arguments = ['command' => 'fos:elastica:populate'];
        if (null !== $message->index) {
            $arguments['--index'] = $message->index;
        }
        $input = new ArrayInput($arguments);
        $code = $application->run($input, new NullOutput());

        if (0 !== $code) {
            // Only the passes started by this very run: other workers may be populating other indices
            $this->populatePassListener->markPendingPassesAsFailed(sprintf('Unexpected command return code %d (expected 0)', $code));
        }
    }
}
