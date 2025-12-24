<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Novu;

use App\Configurator\ConfiguratorInterface;
use App\Service\ServiceWaiter;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class NovuConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private NovuManager $novuManager,
        private ServiceWaiter $serviceWaiter,
    ) {
    }

    public static function getPriority(): int
    {
        return 0;
    }

    public static function getName(): string
    {
        return 'novu';
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $this->serviceWaiter->waitForService($output, getenv('NOVU_API_URL').'/v1/environments', successCodes: [401]);

        $email = getenv('NOVU_DASHBOARD_USERNAME');
        $password = getenv('NOVU_DASHBOARD_PASSWORD');
        if ($this->novuManager->createAccount(
            $email,
            $password,
            'Admin',
            'Dev',
        )) {
            $output->writeln('Novu account created.');
        } else {
            $output->writeln('Novu account already exists.');
        }

        $token = $this->novuManager->getToken(
            $email,
            $password,
        );

        $this->novuManager->updateEnvironment($token);
    }
}
